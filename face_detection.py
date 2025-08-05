import os
import sys
import numpy as np
from PIL import Image, ImageDraw
import cv2
import warnings
from typing import List, Tuple, Optional

# Suppress all warnings
warnings.filterwarnings('ignore')

class FaceDetector:
    def __init__(self):
        """Initialize face detector with OpenCV's Haar cascade"""
        try:
            # Use Haar cascade which is more reliable
            cascade_path = cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
            if os.path.exists(cascade_path):
                self.face_cascade = cv2.CascadeClassifier(cascade_path)
                print(f"Loaded face cascade from: {cascade_path}", file=sys.stderr)
            else:
                # Try alternative paths
                possible_paths = [
                    'haarcascade_frontalface_default.xml',
                    '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
                    '/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml'
                ]
                self.face_cascade = None
                for path in possible_paths:
                    if os.path.exists(path):
                        self.face_cascade = cv2.CascadeClassifier(path)
                        print(f"Loaded face cascade from: {path}", file=sys.stderr)
                        break
                
                if self.face_cascade is None:
                    print("Warning: Could not load face cascade classifier", file=sys.stderr)
                    self.face_cascade = None
        except Exception as e:
            print(f"Error initializing face detector: {str(e)}", file=sys.stderr)
            self.face_cascade = None

    def detect_faces(self, image_path: str) -> List[Tuple[int, int, int, int]]:
        """
        Detect faces in an image using Haar cascade
        Returns list of (x, y, width, height) rectangles
        """
        try:
            print(f"Detecting faces in: {image_path}", file=sys.stderr)
            
            # Read image
            image = cv2.imread(image_path)
            if image is None:
                print(f"Could not read image: {image_path}", file=sys.stderr)
                return []
            
            print(f"Image shape: {image.shape}", file=sys.stderr)
            
            # Convert to grayscale
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            
            # Detect faces with multiple scale factors
            faces = []
            
            if self.face_cascade is not None:
                # Try different scale factors for better detection
                scale_factors = [1.1, 1.05, 1.15]
                min_neighbors_options = [3, 5, 7]
                
                for scale_factor in scale_factors:
                    for min_neighbors in min_neighbors_options:
                        detected_faces = self.face_cascade.detectMultiScale(
                            gray,
                            scaleFactor=scale_factor,
                            minNeighbors=min_neighbors,
                            minSize=(20, 20),
                            maxSize=(300, 300)
                        )
                        
                        if len(detected_faces) > 0:
                            faces.extend(detected_faces.tolist())
                            print(f"Found {len(detected_faces)} faces with scale={scale_factor}, neighbors={min_neighbors}", file=sys.stderr)
                
                # Remove duplicates and merge overlapping faces
                if faces:
                    faces = self._merge_overlapping_faces(faces)
                    print(f"Final face count after merging: {len(faces)}", file=sys.stderr)
                
                return faces
            else:
                print("No face cascade available", file=sys.stderr)
                return []
                
        except Exception as e:
            print(f"Error detecting faces: {str(e)}", file=sys.stderr)
            return []

    def _merge_overlapping_faces(self, faces: List[Tuple[int, int, int, int]]) -> List[Tuple[int, int, int, int]]:
        """Merge overlapping face detections"""
        if not faces:
            return []
        
        # Sort by area (largest first)
        faces = sorted(faces, key=lambda x: x[2] * x[3], reverse=True)
        
        merged = []
        for face in faces:
            x1, y1, w1, h1 = face
            is_overlapping = False
            
            for existing_face in merged:
                x2, y2, w2, h2 = existing_face
                
                # Calculate overlap
                overlap_x = max(0, min(x1 + w1, x2 + w2) - max(x1, x2))
                overlap_y = max(0, min(y1 + h1, y2 + h2) - max(y1, y2))
                overlap_area = overlap_x * overlap_y
                
                # If overlap is more than 50% of smaller face, merge
                smaller_area = min(w1 * h1, w2 * h2)
                if overlap_area > 0.5 * smaller_area:
                    is_overlapping = True
                    break
            
            if not is_overlapping:
                merged.append(face)
        
        return merged

    def extract_face_features(self, image_path: str, face_rect: Tuple[int, int, int, int]) -> np.ndarray:
        """
        Extract features from a detected face region
        """
        try:
            # Read image
            image = cv2.imread(image_path)
            if image is None:
                return np.array([])
            
            x, y, w, h = face_rect
            
            # Extract face region with some padding
            padding = int(min(w, h) * 0.1)  # 10% padding
            x1 = max(0, x - padding)
            y1 = max(0, y - padding)
            x2 = min(image.shape[1], x + w + padding)
            y2 = min(image.shape[0], y + h + padding)
            
            face_region = image[y1:y2, x1:x2]
            
            # Resize to standard size
            face_region = cv2.resize(face_region, (128, 128))
            
            # Convert to grayscale
            gray_face = cv2.cvtColor(face_region, cv2.COLOR_BGR2GRAY)
            
            # Extract comprehensive features
            features = []
            
            # 1. Histogram features
            hist = cv2.calcHist([gray_face], [0], None, [64], [0, 256])
            hist = hist.flatten() / np.sum(hist)
            features.extend(hist)
            
            # 2. Edge features
            edges = cv2.Canny(gray_face, 50, 150)
            edge_hist = cv2.calcHist([edges], [0], None, [32], [0, 256])
            edge_hist = edge_hist.flatten() / (np.sum(edge_hist) + 1e-8)
            features.extend(edge_hist)
            
            # 3. Local Binary Pattern features
            lbp_features = self._compute_lbp_features(gray_face)
            features.extend(lbp_features)
            
            # 4. Gradient features
            grad_x = cv2.Sobel(gray_face, cv2.CV_64F, 1, 0, ksize=3)
            grad_y = cv2.Sobel(gray_face, cv2.CV_64F, 0, 1, ksize=3)
            
            grad_magnitude = np.sqrt(grad_x**2 + grad_y**2)
            grad_direction = np.arctan2(grad_y, grad_x)
            
            # Gradient magnitude histogram
            mag_hist = np.histogram(grad_magnitude, bins=32, range=(0, np.max(grad_magnitude)))[0]
            mag_hist = mag_hist / (np.sum(mag_hist) + 1e-8)
            features.extend(mag_hist)
            
            # Gradient direction histogram
            dir_hist = np.histogram(grad_direction, bins=16, range=(-np.pi, np.pi))[0]
            dir_hist = dir_hist / (np.sum(dir_hist) + 1e-8)
            features.extend(dir_hist)
            
            # 5. Color features
            color_face = cv2.resize(face_region, (64, 64))
            hsv_face = cv2.cvtColor(color_face, cv2.COLOR_BGR2HSV)
            
            # HSV histograms
            h_hist = cv2.calcHist([hsv_face], [0], None, [16], [0, 180])
            s_hist = cv2.calcHist([hsv_face], [1], None, [16], [0, 256])
            v_hist = cv2.calcHist([hsv_face], [2], None, [16], [0, 256])
            
            h_hist = h_hist.flatten() / (np.sum(h_hist) + 1e-8)
            s_hist = s_hist.flatten() / (np.sum(s_hist) + 1e-8)
            v_hist = v_hist.flatten() / (np.sum(v_hist) + 1e-8)
            
            features.extend(h_hist)
            features.extend(s_hist)
            features.extend(v_hist)
            
            # 6. Spatial features
            spatial_features = self._compute_spatial_features(gray_face)
            features.extend(spatial_features)
            
            # Convert to numpy array and normalize
            features = np.array(features, dtype=np.float32)
            features = (features - np.mean(features)) / (np.std(features) + 1e-8)
            
            return features
            
        except Exception as e:
            print(f"Error extracting face features: {str(e)}", file=sys.stderr)
            return np.array([])

    def _compute_lbp_features(self, gray_image: np.ndarray) -> List[float]:
        """Compute Local Binary Pattern features"""
        features = []
        
        # Sample points for faster computation
        step = 4
        lbp_values = []
        
        for i in range(step, gray_image.shape[0] - step, step):
            for j in range(step, gray_image.shape[1] - step, step):
                center = gray_image[i, j]
                code = 0
                
                # Check 8 neighbors
                neighbors = [
                    gray_image[i-step, j-step], gray_image[i-step, j], gray_image[i-step, j+step],
                    gray_image[i, j+step], gray_image[i+step, j+step], gray_image[i+step, j],
                    gray_image[i+step, j-step], gray_image[i, j-step]
                ]
                
                for k, neighbor in enumerate(neighbors):
                    if neighbor >= center:
                        code |= (1 << k)
                
                lbp_values.append(code)
        
        # Compute histogram
        if lbp_values:
            hist, _ = np.histogram(lbp_values, bins=32, range=(0, 256))
            hist = hist / np.sum(hist)
            features.extend(hist)
        else:
            features.extend([0.0] * 32)
        
        return features

    def _compute_spatial_features(self, gray_image: np.ndarray) -> List[float]:
        """Compute spatial features for face structure"""
        features = []
        
        # Divide image into regions and compute statistics
        h, w = gray_image.shape
        regions = [
            gray_image[0:h//2, 0:w//2],      # Top-left
            gray_image[0:h//2, w//2:w],      # Top-right
            gray_image[h//2:h, 0:w//2],      # Bottom-left
            gray_image[h//2:h, w//2:w]       # Bottom-right
        ]
        
        for region in regions:
            # Mean, std, skewness, kurtosis
            mean_val = np.mean(region)
            std_val = np.std(region)
            skewness = np.mean(((region - mean_val) / (std_val + 1e-8)) ** 3)
            kurtosis = np.mean(((region - mean_val) / (std_val + 1e-8)) ** 4) - 3
            
            features.extend([mean_val, std_val, skewness, kurtosis])
        
        return features

def cosine_similarity(vec1: np.ndarray, vec2: np.ndarray) -> float:
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

def get_face_features(image_path: str) -> dict:
    """
    Extract face features from an image
    Returns a dictionary with face information and features
    """
    try:
        detector = FaceDetector()
        
        # Detect faces
        faces = detector.detect_faces(image_path)
        
        if not faces:
            return {
                'has_faces': False,
                'face_count': 0,
                'features': [],
                'face_rectangles': []
            }
        
        # Extract features for each face
        face_features = []
        for face_rect in faces:
            features = detector.extract_face_features(image_path, face_rect)
            if len(features) > 0:
                face_features.append({
                    'rectangle': face_rect,
                    'features': features.tolist()
                })
        
        return {
            'has_faces': True,
            'face_count': len(face_features),
            'features': face_features,
            'face_rectangles': faces
        }
        
    except Exception as e:
        print(f"Error processing image {image_path}: {str(e)}", file=sys.stderr)
        return {
            'has_faces': False,
            'face_count': 0,
            'features': [],
            'face_rectangles': []
        }

def compare_faces(face1_features: List[float], face2_features: List[float]) -> float:
    """
    Compare two face feature vectors with extremely strict matching
    Returns similarity score between 0 and 1
    """
    try:
        vec1 = np.array(face1_features)
        vec2 = np.array(face2_features)
        
        similarity = cosine_similarity(vec1, vec2)
        
        # Apply extremely strict face matching thresholds
        # Only faces that are almost identical should get high scores
        if similarity < 0.95:  # Very high threshold
            similarity = similarity * 0.1  # Heavily penalize anything below 95%
        elif similarity < 0.98:  # Extremely high threshold
            similarity = similarity * 0.3  # Still penalize moderately low similarities
        
        return float(similarity)
        
    except Exception as e:
        print(f"Error comparing faces: {str(e)}", file=sys.stderr)
        return 0.0

if __name__ == "__main__":
    import traceback
    
    if len(sys.argv) < 2:
        print("Usage: python face_detection.py <image_path>")
        sys.exit(1)
    
    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        print(f"File not found: {img_path}")
        sys.exit(1)
    
    try:
        result = get_face_features(img_path)
        
        # Output as JSON-like format for PHP to parse
        if result['has_faces']:
            print(f"FACES_DETECTED:{result['face_count']}")
            for i, face_data in enumerate(result['features']):
                features_str = ','.join(map(str, face_data['features']))
                rect_str = ','.join(map(str, face_data['rectangle']))
                print(f"FACE_{i}:{rect_str}|{features_str}")
        else:
            print("NO_FACES")
            
    except Exception as e:
        print("Error processing image:", e)
        traceback.print_exc()
        sys.exit(1) 