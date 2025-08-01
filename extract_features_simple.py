import os
import sys
import numpy as np
from PIL import Image
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

def get_feature_vector(img_path):
    """
    Extract basic features from image using PIL
    This is a fallback when TensorFlow is not available
    """
    try:
        # Load and resize image
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # Extract basic features:
        # 1. Color histogram (RGB channels)
        # 2. Edge detection (simple gradient)
        # 3. Texture features (local variance)
        
        # Color histogram features (768 features - 256 per channel)
        hist_features = []
        for channel in range(3):
            hist, _ = np.histogram(img_array[:, :, channel], bins=256, range=(0, 256))
            hist_features.extend(hist)
        
        # Simple edge detection (gradient magnitude)
        gray = np.mean(img_array, axis=2)
        grad_x = np.gradient(gray, axis=1)
        grad_y = np.gradient(gray, axis=0)
        gradient_magnitude = np.sqrt(grad_x**2 + grad_y**2)
        
        # Edge histogram (256 features)
        edge_hist, _ = np.histogram(gradient_magnitude, bins=256, range=(0, gradient_magnitude.max()))
        
        # Texture features (local variance in 8x8 blocks)
        texture_features = []
        for i in range(0, 224, 8):
            for j in range(0, 224, 8):
                block = gray[i:i+8, j:j+8]
                texture_features.append(np.var(block))
        
        # Combine all features
        features = np.concatenate([
            hist_features,      # 768 features
            edge_hist,          # 256 features  
            texture_features    # 784 features (28*28 blocks)
        ])
        
        # Normalize features
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        
        # Ensure we have exactly 1280 features to match TensorFlow MobileNetV2 output
        if len(features) > 1280:
            features = features[:1280]  # Truncate to 1280
        elif len(features) < 1280:
            # Pad with zeros if we have fewer features
            features = np.pad(features, (0, 1280 - len(features)), 'constant')
        
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