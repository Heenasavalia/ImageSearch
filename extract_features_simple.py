import os
import sys
import numpy as np
from PIL import Image
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

def get_feature_vector(img_path):
    """
    Extract simple but effective features for object recognition
    Focuses on basic characteristics that distinguish different object types
    """
    try:
        # Load and resize image
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # 1. BASIC COLOR FEATURES
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
        
        # Average brightness
        avg_brightness = np.mean(gray)
        
        # Contrast (standard deviation of grayscale)
        contrast = np.std(gray)
        
        # 3. SIMPLE EDGE DETECTION
        # Calculate gradients
        grad_x = np.diff(gray, axis=1)
        grad_y = np.diff(gray, axis=0)
        
        # Edge strength (use only grad_x for simplicity)
        edge_strength = np.mean(np.abs(grad_x))
        
        # 4. TEXTURE FEATURES
        # Local variance (texture measure)
        local_variance = np.var(gray)
        
        # 5. SHAPE FEATURES
        # Calculate binary image
        threshold = np.mean(gray)
        binary = gray > threshold
        
        # Area ratio (how much of image is object vs background)
        area_ratio = np.sum(binary) / binary.size
        
        # 6. COLOR DISTRIBUTION
        # Create color histogram (simplified)
        color_hist = []
        for channel in range(3):
            hist, _ = np.histogram(img_array[:, :, channel], bins=16, range=(0, 256))
            hist = hist / np.sum(hist)  # Normalize
            color_hist.extend(hist)
        
        # 7. SPATIAL FEATURES
        # Divide image into 4 quadrants and analyze each
        h, w = gray.shape
        quadrants = [
            gray[:h//2, :w//2],      # Top-left
            gray[:h//2, w//2:],      # Top-right
            gray[h//2:, :w//2],      # Bottom-left
            gray[h//2:, w//2:]       # Bottom-right
        ]
        
        quadrant_features = []
        for quad in quadrants:
            quadrant_features.extend([
                np.mean(quad),
                np.std(quad),
                np.max(quad) - np.min(quad)  # Dynamic range
            ])
        
        # 8. OBJECT-SPECIFIC FEATURES
        # These help distinguish between different object types
        
        # Color saturation (flowers are usually more colorful)
        saturation = np.std([avg_r, avg_g, avg_b])
        
        # Texture complexity (animals have more texture than smooth objects)
        texture_complexity = np.std(grad_x)
        
        # Shape complexity (organic vs geometric)
        shape_complexity = 1 - area_ratio  # More complex shapes have lower area ratio
        
        # 9. ENHANCED OBJECT RECOGNITION FEATURES
        # Color dominance (helps identify specific object types)
        color_dominance = max(avg_r, avg_g, avg_b) / (avg_r + avg_g + avg_b + 1e-8)
        
        # Edge density (animals have more edges than flowers)
        edge_density = np.sum(np.abs(grad_x) > np.mean(np.abs(grad_x))) / grad_x.size
        
        # Color harmony (flowers often have harmonious colors)
        color_harmony = 1 - np.std([avg_r, avg_g, avg_b]) / (np.mean([avg_r, avg_g, avg_b]) + 1e-8)
        
        # Texture uniformity (smooth vs rough surfaces)
        texture_uniformity = 1 - np.std(grad_x) / (np.mean(np.abs(grad_x)) + 1e-8)
        
        # 10. ADDITIONAL DISCRIMINATIVE FEATURES
        # Brightness distribution (helps distinguish objects)
        brightness_skew = np.mean((gray - np.mean(gray))**3) / (np.std(gray)**3 + 1e-8)
        
        # Color temperature (warm vs cool colors)
        color_temperature = (avg_r - avg_b) / (avg_r + avg_g + avg_b + 1e-8)
        
        # Spatial complexity (how much the image changes across space)
        spatial_complexity = np.std(np.diff(gray, axis=0)) + np.std(np.diff(gray, axis=1))
        
        # Object size estimate (larger objects vs smaller details)
        object_size = np.sum(binary) / binary.size
        
        # 11. OBJECT CATEGORY PREDICTORS (MOST IMPORTANT)
        # These are the key features that distinguish object categories
        
        # Jewelry indicators (shiny, metallic, geometric)
        jewelry_score = (
            (avg_brightness > 160) * 0.4 +           # Very bright/shiny
            (contrast > 60) * 0.3 +                  # High contrast
            (color_temperature < 0.05) * 0.2 +       # Very cool colors (silver/gold)
            (texture_uniformity > 0.8) * 0.1         # Very smooth surface
        )
        
        # Animal indicators (textured, organic, natural colors, furry)
        animal_score = (
            (texture_complexity > 15) * 0.5 +        # Very high texture (fur)
            (edge_density > 0.4) * 0.3 +             # Many edges (fur details)
            (color_temperature > 0.15) * 0.1 +       # Warm natural colors
            (spatial_complexity > 25) * 0.1          # Complex spatial patterns
        )
        
        # Flower indicators (colorful, organic, centered, vibrant)
        flower_score = (
            (saturation > 40) * 0.5 +                # Very high saturation
            (std_r + std_g + std_b > 80) * 0.3 +     # High color variety
            (color_harmony > 0.7) * 0.1 +            # Very harmonious colors
            (object_size > 0.4) * 0.1                # Large centered object
        )
        
        # Human indicators (symmetrical, skin tones, structured)
        human_score = (
            (color_temperature > 0.2) * 0.5 +        # Very warm skin tones
            (brightness_skew < 0.05) * 0.3 +         # Very even brightness
            (spatial_complexity < 10) * 0.1 +        # Simple patterns
            (texture_uniformity > 0.6) * 0.1         # Smooth skin texture
        )
        
        # 12. COMBINE ALL FEATURES
        basic_features = [
            avg_r, avg_g, avg_b,           # 3 - Average colors
            std_r, std_g, std_b,           # 3 - Color variety
            avg_brightness, contrast,      # 2 - Brightness and contrast
            edge_strength,                 # 1 - Edge strength
            local_variance,                # 1 - Texture
            area_ratio,                    # 1 - Shape area
            saturation,                    # 1 - Color saturation
            texture_complexity,            # 1 - Texture complexity
            shape_complexity,              # 1 - Shape complexity
            color_dominance,               # 1 - Color dominance
            edge_density,                  # 1 - Edge density
            color_harmony,                 # 1 - Color harmony
            texture_uniformity,            # 1 - Texture uniformity
            brightness_skew,               # 1 - Brightness distribution
            color_temperature,             # 1 - Color temperature
            spatial_complexity,            # 1 - Spatial complexity
            object_size,                   # 1 - Object size estimate
            jewelry_score,                 # 1 - Jewelry indicator
            animal_score,                  # 1 - Animal indicator
            flower_score,                  # 1 - Flower indicator
            human_score                    # 1 - Human indicator
        ]
        
        # Combine all features
        features = np.concatenate([
            basic_features,                # 24 basic features
            color_hist,                    # 48 color histogram features (16*3)
            quadrant_features              # 12 quadrant features (4*3)
        ])
        
        # Normalize features
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        features = np.clip(features, -3, 3)
        
        # Ensure we have exactly 1280 features by repeating the pattern
        target_length = 1280
        if len(features) < target_length:
            # Repeat the features to reach target length
            repeats_needed = target_length // len(features) + 1
            features = np.tile(features, repeats_needed)
        
        # Take exactly 1280 features
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