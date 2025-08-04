import os
import sys
import numpy as np
from PIL import Image
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

def get_feature_vector(img_path):
    """
    Extract features specifically designed to distinguish between flowers and animals
    Focuses on the key visual differences between these categories
    """
    try:
        # Load and resize image
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # 1. COLOR ANALYSIS (Most important for flowers vs animals)
        # Average color for each channel
        avg_r = np.mean(img_array[:, :, 0])
        avg_g = np.mean(img_array[:, :, 1])
        avg_b = np.mean(img_array[:, :, 2])
        
        # Color standard deviation (indicates color variety)
        std_r = np.std(img_array[:, :, 0])
        std_g = np.std(img_array[:, :, 1])
        std_b = np.std(img_array[:, :, 2])
        
        # 2. BRIGHTNESS AND CONTRAST
        # Convert to grayscale
        gray = np.mean(img_array, axis=2)
        avg_brightness = np.mean(gray)
        contrast = np.std(gray)
        
        # 3. TEXTURE ANALYSIS (Most important for animals)
        # Calculate gradients
        grad_x = np.diff(gray, axis=1)
        grad_y = np.diff(gray, axis=0)
        
        # Texture measures
        texture_complexity = np.std(grad_x)
        edge_density = np.sum(np.abs(grad_x) > np.mean(np.abs(grad_x))) / grad_x.size
        
        # 4. SHAPE ANALYSIS
        # Calculate binary image
        threshold = np.mean(gray)
        binary = gray > threshold
        area_ratio = np.sum(binary) / binary.size
        
        # 5. FLOWER-SPECIFIC FEATURES
        # Flowers are typically:
        # - Very colorful (high saturation)
        # - Have specific color patterns (red, pink, yellow, etc.)
        # - Often have green backgrounds
        # - Are usually centered and large in the image
        
        # Color saturation
        saturation = np.std([avg_r, avg_g, avg_b])
        
        # Red dominance (roses, tulips, etc.)
        red_dominance = avg_r / (avg_r + avg_g + avg_b + 1e-8)
        
        # Green background (leaves, stems)
        green_dominance = avg_g / (avg_r + avg_g + avg_b + 1e-8)
        
        # Color variety (flowers have many different colors)
        color_variety = std_r + std_g + std_b
        
        # 6. ANIMAL-SPECIFIC FEATURES
        # Animals are typically:
        # - Have fur texture (high texture complexity)
        # - Brown/gray colors (natural tones)
        # - Have many edges (fur details)
        # - Often have eyes, nose, etc. (complex patterns)
        
        # Brown/gray color detection (IMPROVED)
        brown_score = (avg_r > 80) * (avg_g > 60) * (avg_b < 140) * 0.6
        gray_score = (abs(avg_r - avg_g) < 25) * (abs(avg_g - avg_b) < 25) * 0.6
        
        # Texture complexity (fur)
        fur_texture = texture_complexity * edge_density
        
        # 7. CATEGORY PREDICTORS (MULTI-CATEGORY) - IMPROVED DETECTION
        
        # Flower score - based on flower characteristics (IMPROVED)
        flower_score = 0.0
        if saturation > 20:  # High saturation
            flower_score += 0.3
        if red_dominance > 0.25:  # Strong red dominance
            flower_score += 0.4
        if green_dominance > 0.15:  # Green background
            flower_score += 0.2
        if color_variety > 50:  # High color variety
            flower_score += 0.2
        if avg_brightness > 80:  # Bright images
            flower_score += 0.1
        if texture_complexity < 15:  # Smooth texture (flowers are smooth)
            flower_score += 0.2
        
        # Animal score - based on animal characteristics (IMPROVED)
        animal_score = 0.0
        if texture_complexity > 8:  # High texture (fur)
            animal_score += 0.3
        if edge_density > 0.2:  # Many edges (fur details)
            animal_score += 0.3
        if brown_score > 0.1:  # Brown colors
            animal_score += 0.4
        if gray_score > 0.1:  # Gray colors
            animal_score += 0.3
        if fur_texture > 2:  # Fur texture
            animal_score += 0.3
        if contrast > 30:  # Moderate contrast
            animal_score += 0.2
        if avg_brightness < 120:  # Not too bright
            animal_score += 0.2
        
        # Jewelry score - based on jewelry characteristics (IMPROVED)
        jewelry_score = 0.0
        if avg_brightness > 140:  # Very bright/shiny
            jewelry_score += 0.4
        if contrast > 50:  # High contrast
            jewelry_score += 0.3
        if texture_complexity < 10:  # Very smooth surface
            jewelry_score += 0.3
        if color_variety < 60:  # Limited color variety
            jewelry_score += 0.2
        if saturation < 30:  # Less colorful
            jewelry_score += 0.2
        if red_dominance < 0.3:  # Not red dominant
            jewelry_score += 0.1
        if green_dominance < 0.2:  # Not green
            jewelry_score += 0.1
        
        # Human score - based on human characteristics (IMPROVED)
        human_score = 0.0
        if avg_brightness > 80 and avg_brightness < 140:  # Moderate brightness
            human_score += 0.3
        if contrast < 40:  # Low contrast (smooth skin)
            human_score += 0.3
        if texture_complexity < 20:  # Smooth texture
            human_score += 0.3
        if red_dominance > 0.3 and red_dominance < 0.6:  # Skin tone range
            human_score += 0.4
        if color_variety < 80:  # Limited color variety
            human_score += 0.2
        if green_dominance < 0.25:  # Not green dominant
            human_score += 0.2
        if saturation < 50:  # Moderate saturation
            human_score += 0.2
        
        # 8. BASIC FEATURES (for general similarity)
        basic_features = [
            avg_r, avg_g, avg_b,           # 3 - Average colors
            std_r, std_g, std_b,           # 3 - Color variety
            avg_brightness, contrast,      # 2 - Brightness and contrast
            texture_complexity,            # 1 - Texture complexity
            edge_density,                  # 1 - Edge density
            area_ratio,                    # 1 - Shape area
            saturation,                    # 1 - Color saturation
            red_dominance,                 # 1 - Red dominance
            green_dominance,               # 1 - Green dominance
            color_variety,                 # 1 - Color variety
            brown_score,                   # 1 - Brown color
            gray_score,                    # 1 - Gray color
            fur_texture,                   # 1 - Fur texture
            flower_score,                  # 1 - Flower indicator
            animal_score,                  # 1 - Animal indicator
            jewelry_score,                 # 1 - Jewelry indicator
            human_score                    # 1 - Human indicator
        ]
        
        # 9. COLOR HISTOGRAM (simplified)
        color_hist = []
        for channel in range(3):
            hist, _ = np.histogram(img_array[:, :, channel], bins=8, range=(0, 256))
            hist = hist / np.sum(hist)  # Normalize
            color_hist.extend(hist)
        
        # 10. COMBINE ALL FEATURES
        features = np.concatenate([
            basic_features,                # 18 basic features
            color_hist                     # 24 color histogram features (8*3)
        ])
        
        # Debug: Print category scores
        print(f"DEBUG: Flower={flower_score:.3f}, Animal={animal_score:.3f}, Jewelry={jewelry_score:.3f}, Human={human_score:.3f}", file=sys.stderr)
        
        # Normalize features
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        features = np.clip(features, -3, 3)
        
        # Ensure we have exactly 1280 features by repeating the pattern
        target_length = 1280
        if len(features) < target_length:
            repeats_needed = target_length // len(features) + 1
            features = np.tile(features, repeats_needed)
        
        features = features[:target_length]
        
        return features
        
    except Exception as e:
        print(f"Error processing image {img_path}: {str(e)}", file=sys.stderr)
        raise

if __name__ == "__main__":
    import traceback
    if len(sys.argv) < 2:
        print("Usage: python extract_features_simple.py <image_path>")
        sys.exit(1)
    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        print(f"File not found: {img_path}")
        sys.exit(1)
    try:
        features = get_feature_vector(img_path)
        print(','.join(map(str, features)))
    except Exception as e:
        print("Error processing image:", e)
        traceback.print_exc()
        sys.exit(1) 