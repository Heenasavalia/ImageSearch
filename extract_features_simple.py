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
        
        # Brown/gray color detection
        brown_score = (avg_r > 100) * (avg_g > 80) * (avg_b < 120) * 0.5
        gray_score = (abs(avg_r - avg_g) < 20) * (abs(avg_g - avg_b) < 20) * 0.5
        
        # Texture complexity (fur)
        fur_texture = texture_complexity * edge_density
        
        # 7. CATEGORY PREDICTORS (MULTI-CATEGORY)
        
        # Flower score - based on flower characteristics
        flower_score = (
            (saturation > 50) * 0.4 +                    # Very high saturation
            (red_dominance > 0.4) * 0.4 +                # Strong red/pink dominance
            (green_dominance > 0.25) * 0.2 +             # Green background
            (color_variety > 100) * 0.2                  # High color variety
        )
        
        # Animal score - based on animal characteristics
        animal_score = (
            (texture_complexity > 20) * 0.5 +            # Very high texture (fur)
            (edge_density > 0.5) * 0.3 +                 # Many edges
            (brown_score + gray_score) * 0.2 +           # Natural colors
            (fur_texture > 12) * 0.1                     # Strong fur texture
        )
        
        # Jewelry score - based on jewelry characteristics
        jewelry_score = (
            (avg_brightness > 160) * 0.4 +               # Very bright/shiny
            (contrast > 60) * 0.3 +                      # High contrast
            (texture_complexity < 10) * 0.2 +            # Smooth surface
            (color_variety < 50) * 0.1                   # Limited color variety
        )
        
        # Human score - based on human characteristics
        human_score = (
            (avg_brightness > 100) * 0.3 +               # Moderate brightness
            (contrast < 40) * 0.3 +                      # Low contrast (smooth skin)
            (texture_complexity < 15) * 0.2 +            # Smooth texture
            (red_dominance > 0.35) * 0.2                 # Skin tone
        )
        
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