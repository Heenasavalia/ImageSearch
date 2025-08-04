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
    Extract highly discriminative visual features for exact category matching
    Focuses on features that clearly distinguish between flowers, animals, jewelry, etc.
    """
    try:
        # Load and resize image to consistent size
        img = Image.open(img_path).convert('RGB')
        img = img.resize((224, 224))
        
        # Convert to numpy array
        img_array = np.array(img)
        
        # 1. DOMINANT COLOR ANALYSIS (Most important for category distinction)
        # Calculate dominant colors using histogram peaks
        dominant_colors = extract_dominant_colors_advanced(img_array)
        
        # 2. COLOR DISTRIBUTION FEATURES
        # Analyze color distribution patterns that distinguish categories
        color_features = analyze_color_distribution(img_array)
        
        # 3. TEXTURE ANALYSIS (Critical for animal vs flower distinction)
        # Animals have fur texture, flowers have smooth petals
        texture_features = analyze_texture_patterns(img_array)
        
        # 4. SHAPE AND STRUCTURE FEATURES
        # Flowers are usually centered and have specific shapes
        # Animals have more complex body structures
        shape_features = analyze_shape_structure(img_array)
        
        # 5. BRIGHTNESS AND CONTRAST PATTERNS
        # Jewelry is very bright and shiny
        # Flowers have natural lighting
        # Animals have more varied lighting
        lighting_features = analyze_lighting_patterns(img_array)
        
        # 6. EDGE DENSITY AND PATTERNS
        # Different categories have different edge patterns
        edge_features = analyze_edge_patterns(img_array)
        
        # 7. SPATIAL DISTRIBUTION
        # How colors and features are distributed across the image
        spatial_features = analyze_spatial_distribution(img_array)
        
        # Combine all features
        features = []
        features.extend(dominant_colors)      # 30 features
        features.extend(color_features)       # 20 features
        features.extend(texture_features)     # 25 features
        features.extend(shape_features)       # 15 features
        features.extend(lighting_features)    # 10 features
        features.extend(edge_features)        # 20 features
        features.extend(spatial_features)     # 40 features
        
        # Convert to numpy array and normalize
        features = np.array(features, dtype=np.float32)
        
        # Apply aggressive normalization to make differences more pronounced
        features = (features - np.mean(features)) / (np.std(features) + 1e-8)
        features = np.clip(features, -5, 5)  # More aggressive clipping
        
        # Ensure consistent length (160 features total)
        target_length = 160
        if len(features) < target_length:
            features = np.pad(features, (0, target_length - len(features)), 'constant')
        elif len(features) > target_length:
            features = features[:target_length]
        
        return features
        
    except Exception as e:
        print(f"Error processing image {img_path}: {str(e)}", file=sys.stderr)
        raise

def extract_dominant_colors_advanced(img_array):
    """Extract dominant colors with advanced analysis"""
    features = []
    
    # Analyze each color channel separately
    for channel in range(3):
        channel_data = img_array[:, :, channel]
        
        # Find color peaks (dominant colors)
        hist, bins = np.histogram(channel_data, bins=16, range=(0, 256))
        peaks = find_peaks(hist)
        
        # Extract peak characteristics
        if len(peaks) > 0:
            peak_values = [hist[p] for p in peaks]
            peak_positions = [bins[p] for p in peaks]
            
            # Top 3 dominant colors for this channel
            for i in range(3):
                if i < len(peak_values):
                    features.append(peak_values[i] / np.sum(hist))
                    features.append(peak_positions[i] / 256.0)
                else:
                    features.extend([0.0, 0.0])
        else:
            features.extend([0.0, 0.0] * 3)
    
    return features

def find_peaks(histogram, min_height=0.1):
    """Find peaks in histogram"""
    peaks = []
    for i in range(1, len(histogram) - 1):
        if (histogram[i] > histogram[i-1] and 
            histogram[i] > histogram[i+1] and 
            histogram[i] > np.max(histogram) * min_height):
            peaks.append(i)
    return sorted(peaks, key=lambda x: histogram[x], reverse=True)[:3]

def analyze_color_distribution(img_array):
    """Analyze color distribution patterns"""
    features = []
    
    # Color variance analysis
    for channel in range(3):
        channel_data = img_array[:, :, channel]
        features.append(np.std(channel_data))
        features.append(np.percentile(channel_data, 25))
        features.append(np.percentile(channel_data, 75))
        features.append(np.max(channel_data) - np.min(channel_data))
    
    # Color correlation analysis
    r_g_corr = np.corrcoef(img_array[:, :, 0].flatten(), img_array[:, :, 1].flatten())[0, 1]
    r_b_corr = np.corrcoef(img_array[:, :, 0].flatten(), img_array[:, :, 2].flatten())[0, 1]
    g_b_corr = np.corrcoef(img_array[:, :, 1].flatten(), img_array[:, :, 2].flatten())[0, 1]
    
    features.extend([r_g_corr, r_b_corr, g_b_corr])
    
    return features

def analyze_texture_patterns(img_array):
    """Analyze texture patterns to distinguish categories"""
    features = []
    
    # Convert to grayscale for texture analysis
    gray = np.mean(img_array, axis=2)
    
    # Calculate gradients
    grad_x = np.diff(gray, axis=1)
    grad_y = np.diff(gray, axis=0)
    
    # Ensure both gradients have the same shape for edge magnitude calculation
    # Use the smaller of the two dimensions
    min_h, min_w = min(grad_x.shape[0], grad_y.shape[0]), min(grad_x.shape[1], grad_y.shape[1])
    grad_x = grad_x[:min_h, :min_w]
    grad_y = grad_y[:min_h, :min_w]
    
    # Texture complexity measures
    features.append(np.std(grad_x))  # Horizontal texture
    features.append(np.std(grad_y))  # Vertical texture
    features.append(np.mean(np.abs(grad_x)))  # Average horizontal gradient
    features.append(np.mean(np.abs(grad_y)))  # Average vertical gradient
    
    # Edge density
    edge_magnitude = np.sqrt(grad_x**2 + grad_y**2)
    features.append(np.mean(edge_magnitude))
    features.append(np.std(edge_magnitude))
    
    # Local Binary Pattern approximation
    lbp_features = compute_simple_lbp(gray)
    features.extend(lbp_features)
    
    return features

def compute_simple_lbp(gray_image):
    """Compute simplified Local Binary Pattern"""
    features = []
    
    # Sample points for faster computation
    step = 4
    lbp_values = []
    
    for i in range(step, gray_image.shape[0] - step, step):
        for j in range(step, gray_image.shape[1] - step, step):
            center = gray_image[i, j]
            code = 0
            
            # Check 4 neighbors (simplified)
            neighbors = [
                gray_image[i-step, j], gray_image[i+step, j],
                gray_image[i, j-step], gray_image[i, j+step]
            ]
            
            for k, neighbor in enumerate(neighbors):
                if neighbor >= center:
                    code |= (1 << k)
            
            lbp_values.append(code)
    
    # Compute histogram
    if lbp_values:
        hist, _ = np.histogram(lbp_values, bins=16, range=(0, 16))
        hist = hist / np.sum(hist)
        features.extend(hist)
    else:
        features.extend([0.0] * 16)
    
    return features

def analyze_shape_structure(img_array):
    """Analyze shape and structure patterns"""
    features = []
    
    # Convert to grayscale
    gray = np.mean(img_array, axis=2)
    
    # Threshold to get binary image
    threshold = np.mean(gray)
    binary = gray > threshold
    
    # Shape analysis
    features.append(np.sum(binary) / binary.size)  # Area ratio
    
    # Center of mass analysis
    y_coords, x_coords = np.where(binary)
    if len(y_coords) > 0:
        center_y = np.mean(y_coords) / gray.shape[0]
        center_x = np.mean(x_coords) / gray.shape[1]
        features.extend([center_y, center_x])
    else:
        features.extend([0.5, 0.5])
    
    # Symmetry analysis
    symmetry_score = analyze_symmetry(binary)
    features.append(symmetry_score)
    
    # Compactness
    if len(y_coords) > 0:
        perimeter = estimate_perimeter(binary)
        area = len(y_coords)
        compactness = (4 * np.pi * area) / (perimeter * perimeter) if perimeter > 0 else 0
        features.append(compactness)
    else:
        features.append(0.0)
    
    return features

def analyze_symmetry(binary_image):
    """Analyze symmetry of the binary image"""
    h, w = binary_image.shape
    
    # Vertical symmetry
    left_half = binary_image[:, :w//2]
    right_half = binary_image[:, w//2:]
    if right_half.shape[1] != left_half.shape[1]:
        right_half = right_half[:, :-1]
    
    vertical_symmetry = np.sum(left_half == np.fliplr(right_half)) / left_half.size
    
    # Horizontal symmetry
    top_half = binary_image[:h//2, :]
    bottom_half = binary_image[h//2:, :]
    if bottom_half.shape[0] != top_half.shape[0]:
        bottom_half = bottom_half[:-1, :]
    
    horizontal_symmetry = np.sum(top_half == np.flipud(bottom_half)) / top_half.size
    
    return (vertical_symmetry + horizontal_symmetry) / 2

def estimate_perimeter(binary_image):
    """Estimate perimeter of binary image"""
    # Simple edge detection
    edges = np.zeros_like(binary_image)
    edges[:-1, :] |= binary_image[1:, :] != binary_image[:-1, :]
    edges[:, :-1] |= binary_image[:, 1:] != binary_image[:, :-1]
    return np.sum(edges)

def analyze_lighting_patterns(img_array):
    """Analyze lighting and brightness patterns"""
    features = []
    
    # Overall brightness
    gray = np.mean(img_array, axis=2)
    features.append(np.mean(gray))
    features.append(np.std(gray))
    
    # Brightness distribution
    features.append(np.percentile(gray, 10))
    features.append(np.percentile(gray, 90))
    
    # Contrast
    features.append(np.max(gray) - np.min(gray))
    
    # Brightness uniformity (low for jewelry, high for flowers)
    brightness_std = np.std(gray)
    brightness_mean = np.mean(gray)
    uniformity = 1.0 / (1.0 + brightness_std / (brightness_mean + 1e-8))
    features.append(uniformity)
    
    return features

def analyze_edge_patterns(img_array):
    """Analyze edge patterns and distributions"""
    features = []
    
    gray = np.mean(img_array, axis=2)
    
    # Calculate gradients
    grad_x = np.diff(gray, axis=1)
    grad_y = np.diff(gray, axis=0)
    
    # Ensure both gradients have the same shape
    min_h, min_w = min(grad_x.shape[0], grad_y.shape[0]), min(grad_x.shape[1], grad_y.shape[1])
    grad_x = grad_x[:min_h, :min_w]
    grad_y = grad_y[:min_h, :min_w]
    
    # Edge magnitude
    edge_magnitude = np.sqrt(grad_x**2 + grad_y**2)
    
    # Edge statistics
    features.append(np.mean(edge_magnitude))
    features.append(np.std(edge_magnitude))
    features.append(np.max(edge_magnitude))
    features.append(np.percentile(edge_magnitude, 90))
    
    # Edge direction analysis
    edge_direction = np.arctan2(grad_y, grad_x)
    
    # Direction histogram
    for i in range(4):
        angle_range = (-np.pi + i * np.pi/2, -np.pi + (i+1) * np.pi/2)
        mask = (edge_direction >= angle_range[0]) & (edge_direction < angle_range[1])
        features.append(np.sum(mask) / edge_direction.size)
    
    # Edge density
    strong_edges = edge_magnitude > np.percentile(edge_magnitude, 80)
    features.append(np.sum(strong_edges) / strong_edges.size)
    
    return features

def analyze_spatial_distribution(img_array):
    """Analyze spatial distribution of features"""
    features = []
    
    # Divide image into 4x4 grid
    h, w = img_array.shape[:2]
    grid_h, grid_w = h // 4, w // 4
    
    for i in range(4):
        for j in range(4):
            # Extract grid cell
            start_h, end_h = i * grid_h, (i + 1) * grid_h
            start_w, end_w = j * grid_w, (j + 1) * grid_w
            
            cell = img_array[start_h:end_h, start_w:end_w]
            
            # Average color for this cell
            avg_color = np.mean(cell, axis=(0, 1))
            features.extend(avg_color)
    
    return features

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