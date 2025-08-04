import os
import sys
import numpy as np
from PIL import Image
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

def cosine_similarity(vec1, vec2):
    """Calculate cosine similarity between two vectors"""
    try:
        # Ensure both vectors have the same length
        min_len = min(len(vec1), len(vec2))
        vec1 = vec1[:min_len]
        vec2 = vec2[:min_len]
        
        # Calculate dot product
        dot_product = np.dot(vec1, vec2)
        
        # Calculate magnitudes
        mag1 = np.linalg.norm(vec1)
        mag2 = np.linalg.norm(vec2)
        
        # Avoid division by zero
        if mag1 == 0 or mag2 == 0:
            return 0.0
        
        # Calculate cosine similarity
        similarity = dot_product / (mag1 * mag2)
        
        # Convert from [-1, 1] to [0, 1] range
        similarity = (similarity + 1) / 2
        
        return float(similarity)
    except Exception as e:
        print(f"Error calculating similarity: {str(e)}", file=sys.stderr)
        return 0.0

def get_feature_vector(img_path):
    """
    Extract comprehensive visual features for exact image matching
    Focuses on pixel-level similarity, color distribution, texture, and shape
    """
    try:
        # Load and resize image to consistent size
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # 1. PIXEL-LEVEL FEATURES (Most important for exact matching)
        # Reshape to 1D array for direct pixel comparison
        pixels = img_array.reshape(-1, 3)
        
        # 2. COLOR HISTOGRAM (Detailed color distribution)
        color_hist = []
        for channel in range(3):
            hist, _ = np.histogram(img_array[:, :, channel], bins=32, range=(0, 256))
            hist = hist / np.sum(hist)  # Normalize
            color_hist.extend(hist)
        
        # 3. AVERAGE COLOR FEATURES
        avg_r = np.mean(img_array[:, :, 0])
        avg_g = np.mean(img_array[:, :, 1])
        avg_b = np.mean(img_array[:, :, 2])
        
        # 4. COLOR STANDARD DEVIATION (color variety)
        std_r = np.std(img_array[:, :, 0])
        std_g = np.std(img_array[:, :, 1])
        std_b = np.std(img_array[:, :, 2])
        
        # 5. BRIGHTNESS AND CONTRAST
        gray = np.mean(img_array, axis=2)
        avg_brightness = np.mean(gray)
        contrast = np.std(gray)
        
        # 6. TEXTURE FEATURES (using gradients)
        grad_x = np.diff(gray, axis=1)
        grad_y = np.diff(gray, axis=0)
        
        texture_complexity = np.std(grad_x)
        edge_density = np.sum(np.abs(grad_x) > np.mean(np.abs(grad_x))) / grad_x.size
        
        # 7. SHAPE FEATURES
        threshold = np.mean(gray)
        binary = gray > threshold
        area_ratio = np.sum(binary) / binary.size
        
        # 8. LOCAL BINARY PATTERN (LBP) for texture
        lbp_features = compute_lbp(gray)
        
        # 9. DOMINANT COLORS (K-means clustering simulation)
        dominant_colors = extract_dominant_colors(pixels)
        
        # 10. EDGE DETECTION FEATURES
        edge_features = compute_edge_features(gray)
        
        # 11. SPATIAL FEATURES (grid-based analysis)
        spatial_features = compute_spatial_features(img_array)
        
        # Combine all features
        features = []
        
        # Color histogram (96 features)
        features.extend(color_hist)
        
        # Basic color features (6 features)
        features.extend([avg_r, avg_g, avg_b, std_r, std_g, std_b])
        
        # Brightness and contrast (2 features)
        features.extend([avg_brightness, contrast])
        
        # Texture features (2 features)
        features.extend([texture_complexity, edge_density])
        
        # Shape features (1 feature)
        features.extend([area_ratio])
        
        # LBP features (256 features)
        features.extend(lbp_features)
        
        # Dominant colors (15 features - 5 colors x 3 channels)
        features.extend(dominant_colors)
        
        # Edge features (8 features)
        features.extend(edge_features)
        
        # Spatial features (64 features)
        features.extend(spatial_features)
        
        # Convert to numpy array and normalize
        features = np.array(features, dtype=np.float32)
        
        # Normalize features to prevent any single feature from dominating
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        features = np.clip(features, -3, 3)
        
        # Ensure consistent length (450 features total)
        target_length = 450
        if len(features) < target_length:
            # Pad with zeros
            features = np.pad(features, (0, target_length - len(features)), 'constant')
        elif len(features) > target_length:
            # Truncate
            features = features[:target_length]
        
        return features
        
    except Exception as e:
        print(f"Error processing image {img_path}: {str(e)}", file=sys.stderr)
        raise

def compute_lbp(gray_image):
    """Compute Local Binary Pattern for texture analysis"""
    try:
        # Simplified LBP implementation
        lbp = np.zeros_like(gray_image)
        
        for i in range(1, gray_image.shape[0] - 1):
            for j in range(1, gray_image.shape[1] - 1):
                center = gray_image[i, j]
                code = 0
                
                # Check 8 neighbors
                neighbors = [
                    gray_image[i-1, j-1], gray_image[i-1, j], gray_image[i-1, j+1],
                    gray_image[i, j+1], gray_image[i+1, j+1], gray_image[i+1, j],
                    gray_image[i+1, j-1], gray_image[i, j-1]
                ]
                
                for k, neighbor in enumerate(neighbors):
                    if neighbor >= center:
                        code |= (1 << k)
                
                lbp[i, j] = code
        
        # Compute histogram
        hist, _ = np.histogram(lbp, bins=256, range=(0, 256))
        hist = hist / np.sum(hist)
        
        return hist
    except:
        # Fallback: return zeros if LBP fails
        return np.zeros(256)

def extract_dominant_colors(pixels, n_colors=5):
    """Extract dominant colors using simplified clustering"""
    try:
        # Sample pixels to speed up processing
        if len(pixels) > 1000:
            indices = np.random.choice(len(pixels), 1000, replace=False)
            sample_pixels = pixels[indices]
        else:
            sample_pixels = pixels
        
        # Simple k-means approximation using quantiles
        dominant_colors = []
        for channel in range(3):
            channel_values = sample_pixels[:, channel]
            quantiles = np.percentile(channel_values, [20, 40, 60, 80, 100])
            dominant_colors.extend(quantiles)
        
        return dominant_colors
    except:
        # Fallback: return average colors
        return [np.mean(pixels[:, i]) for i in range(3)] * 5

def compute_edge_features(gray_image):
    """Compute edge-based features"""
    try:
        # Simple edge detection using gradients
        grad_x = np.diff(gray_image, axis=1)
        grad_y = np.diff(gray_image, axis=0)
        
        # Edge magnitude
        edge_magnitude = np.sqrt(grad_x**2 + grad_y**2)
        
        # Edge direction
        edge_direction = np.arctan2(grad_y, grad_x)
        
        # Edge statistics
        edge_mean = np.mean(edge_magnitude)
        edge_std = np.std(edge_magnitude)
        edge_max = np.max(edge_magnitude)
        edge_min = np.min(edge_magnitude)
        
        # Direction histogram (simplified)
        dir_hist = np.histogram(edge_direction, bins=4, range=(-np.pi, np.pi))[0]
        dir_hist = dir_hist / np.sum(dir_hist)
        
        return [edge_mean, edge_std, edge_max, edge_min] + list(dir_hist)
    except:
        return [0.0] * 8

def compute_spatial_features(img_array):
    """Compute spatial features by dividing image into grid"""
    try:
        # Divide image into 8x8 grid
        h, w = img_array.shape[:2]
        grid_h, grid_w = h // 8, w // 8
        
        spatial_features = []
        
        for i in range(8):
            for j in range(8):
                # Extract grid cell
                start_h, end_h = i * grid_h, (i + 1) * grid_h
                start_w, end_w = j * grid_w, (j + 1) * grid_w
                
                cell = img_array[start_h:end_h, start_w:end_w]
                
                # Compute average color for this cell
                avg_color = np.mean(cell, axis=(0, 1))
                spatial_features.extend(avg_color)
        
        return spatial_features
    except:
        return [0.0] * 64

def calculate_image_similarity(features1, features2):
    """
    Calculate similarity between two feature vectors
    Returns a value between 0 and 1, where 1 is identical
    """
    try:
        # Ensure both vectors have the same length
        min_len = min(len(features1), len(features2))
        features1 = features1[:min_len]
        features2 = features2[:min_len]
        
        # Calculate cosine similarity
        similarity = cosine_similarity(features1, features2)
        
        return float(similarity)
    except Exception as e:
        print(f"Error calculating similarity: {str(e)}", file=sys.stderr)
        return 0.0

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