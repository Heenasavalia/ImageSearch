import os
import sys
import numpy as np
from PIL import Image
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

def get_feature_vector(img_path):
    """
    Extract advanced features for object recognition
    Focuses on characteristics that distinguish different object categories
    """
    try:
        # Load and resize image
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # 1. ADVANCED COLOR ANALYSIS
        # Convert to different color spaces for better analysis
        from PIL import ImageCms
        
        # RGB analysis
        avg_r = np.mean(img_array[:, :, 0])
        avg_g = np.mean(img_array[:, :, 1])
        avg_b = np.mean(img_array[:, :, 2])
        
        # Color ratios (important for object classification)
        red_ratio = avg_r / (avg_r + avg_g + avg_b + 1e-8)
        green_ratio = avg_g / (avg_r + avg_g + avg_b + 1e-8)
        blue_ratio = avg_b / (avg_r + avg_g + avg_b + 1e-8)
        
        # Color standard deviation (indicates color variety)
        std_r = np.std(img_array[:, :, 0])
        std_g = np.std(img_array[:, :, 1])
        std_b = np.std(img_array[:, :, 2])
        
        # Color dominance features
        max_color = max(avg_r, avg_g, avg_b)
        min_color = min(avg_r, avg_g, avg_b)
        color_range = max_color - min_color
        color_balance = (max_color - min_color) / (max_color + 1e-8)
        
        # 2. TEXTURE AND PATTERN ANALYSIS
        # Convert to grayscale
        gray = np.mean(img_array, axis=2)
        
        # Multi-scale texture analysis
        texture_features = []
        # Store gradients from first scale for edge detection
        first_scale_grad_x = None
        first_scale_grad_y = None
        
        for scale in [1, 2, 4]:
            # Downsample
            if scale > 1:
                h, w = gray.shape
                small_gray = gray[::scale, ::scale]
            else:
                small_gray = gray
            
            # Calculate gradients
            grad_x = np.diff(small_gray, axis=1)
            grad_y = np.diff(small_gray, axis=0)
            
            # Ensure gradients have the same shape for later operations
            h_x, w_x = grad_x.shape
            h_y, w_y = grad_y.shape
            min_h = min(h_x, h_y)
            min_w = min(w_x, w_y)
            
            # Crop both gradients to the same size
            grad_x = grad_x[:min_h, :min_w]
            grad_y = grad_y[:min_h, :min_w]
            
            # Store first scale gradients for edge detection
            if scale == 1:
                first_scale_grad_x = grad_x
                first_scale_grad_y = grad_y
            
            # Texture measures
            texture_features.extend([
                np.mean(np.abs(grad_x)),  # Horizontal texture
                np.mean(np.abs(grad_y)),  # Vertical texture
                np.std(grad_x),           # Texture variation
                np.std(grad_y),
                np.mean(grad_x**2),       # Texture energy
                np.mean(grad_y**2)
            ])
        
        # 3. SHAPE AND STRUCTURE ANALYSIS
        # Edge detection using multiple thresholds
        edge_features = []
        for threshold in [0.1, 0.3, 0.5]:
            # Create binary edge map using first scale gradients
            edge_map = (np.abs(first_scale_grad_x) > threshold * np.max(np.abs(first_scale_grad_x))) | \
                      (np.abs(first_scale_grad_y) > threshold * np.max(np.abs(first_scale_grad_y)))
            edge_features.extend([
                np.sum(edge_map) / edge_map.size,  # Edge density
                np.std(edge_map.astype(float))     # Edge distribution
            ])
        
        # 4. OBJECT-SPECIFIC FEATURES
        # These are designed to distinguish between different object categories
        
        # Flower detection features
        # Flowers typically have high color saturation and specific color patterns
        saturation = np.std([avg_r, avg_g, avg_b])
        color_variety = np.std([std_r, std_g, std_b])
        
        # Brightness distribution (flowers vs objects)
        brightness_hist, _ = np.histogram(gray, bins=16, range=(0, 255))
        brightness_hist = brightness_hist / np.sum(brightness_hist)
        brightness_entropy = -np.sum(brightness_hist * np.log(brightness_hist + 1e-8))
        
        # Color temperature (warm vs cool)
        color_temp = (avg_r - avg_b) / (avg_r + avg_g + avg_b + 1e-8)
        
        # Spatial distribution (flowers are often centered, humans are vertical)
        # Divide image into 9 regions (3x3 grid)
        h, w = gray.shape
        regions = []
        for i in range(3):
            for j in range(3):
                region = gray[i*h//3:(i+1)*h//3, j*w//3:(j+1)*w//3]
                regions.append(np.mean(region))
        
        # Spatial symmetry (flowers are more symmetric than humans)
        spatial_symmetry = 1 - np.std(regions) / (np.mean(regions) + 1e-8)
        
        # 5. ADVANCED COLOR HISTOGRAMS
        # Create detailed color histograms for each channel
        color_hist_features = []
        for channel in range(3):
            hist, _ = np.histogram(img_array[:, :, channel], bins=64, range=(0, 256))
            hist = hist / np.sum(hist)
            
            # Extract histogram statistics
            color_hist_features.extend([
                np.mean(hist),           # Average color intensity
                np.std(hist),            # Color variation
                np.max(hist),            # Peak color
                np.argmax(hist) / 64,    # Peak position (normalized)
                np.sum(hist > np.mean(hist)),  # Number of dominant colors
                -np.sum(hist * np.log(hist + 1e-8))  # Color entropy
            ])
        
        # 6. OBJECT CATEGORY PREDICTORS
        # Features specifically designed to distinguish object categories
        
        # Flower indicators
        flower_score = (
            saturation * 0.3 +                    # High saturation
            color_variety * 0.2 +                 # Color variety
            (1 - spatial_symmetry) * 0.3 +        # Less symmetric
            (green_ratio > 0.4) * 0.2             # Often green background
        )
        
        # Human indicators
        human_score = (
            spatial_symmetry * 0.4 +              # More symmetric
            (color_temp > 0.1) * 0.3 +            # Warm colors (skin)
            (brightness_entropy > 4.0) * 0.3      # Complex brightness patterns
        )
        
        # Animal indicators
        animal_score = (
            np.mean(texture_features[:6]) * 0.4 + # High texture
            (edge_features[0] > 0.1) * 0.3 +      # Many edges
            (color_balance < 0.5) * 0.3           # Less color balance
        )
        
        # 7. COMBINE ALL FEATURES
        basic_features = [
            # Color features
            avg_r, avg_g, avg_b, red_ratio, green_ratio, blue_ratio,
            color_range, color_balance, saturation, color_variety,
            
            # Texture features (18 features from multi-scale analysis)
            *texture_features,
            
            # Edge features (6 features from multiple thresholds)
            *edge_features,
            
            # Object-specific features
            brightness_entropy, color_temp, spatial_symmetry,
            flower_score, human_score, animal_score,
            
            # Region features (9 regions)
            *regions
        ]
        
        # Combine all features
        features = np.concatenate([
            basic_features,                # ~60 basic features
            color_hist_features,           # 36 color histogram features (6*6)
            brightness_hist               # 16 brightness histogram features
        ])
        
        # Normalize features
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        features = np.clip(features, -3, 3)
        
        # Ensure we have exactly 1280 features
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