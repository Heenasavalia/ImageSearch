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
        # Try to load the face cascade classifier
        cascade_path = cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
        if os.path.exists(cascade_path):
            self.face_cascade = cv2.CascadeClassifier(cascade_path)
        else:
            # Fallback: try to find it in common locations
            possible_paths = [
                'haarcascade_frontalface_default.xml',
                '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
                '/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml'
            ]
            self.face_cascade = None
            for path in possible_paths:
                if os.path.exists(path):
                    self.face_cascade = cv2.CascadeClassifier(path)
                    break
            
            if self.face_cascade is None:
                print("Warning: Could not load face cascade classifier", file=sys.stderr)
                self.face_cascade = None

    def detect_faces(self, image_path: str) -> List[Tuple[int, int, int, int]]:
        """
        Detect faces in an image
        Returns list of (x, y, width, height) rectangles
        """
        try:
            # Read image
            image = cv2.imread(image_path)
            if image is None:
                return []
            
            # Convert to grayscale
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            
            # Detect faces
            if self.face_cascade is not None:
                faces = self.face_cascade.detectMultiScale(
                    gray,
                    scaleFactor=1.1,
                    minNeighbors=5,
                    minSize=(30, 30)
                )
                return faces.tolist()
            else:
                # Fallback: simple skin color detection
                return self._detect_faces_by_skin_color(image)
                
        except Exception as e:
            print(f"Error detecting faces: {str(e)}", file=sys.stderr)
            return []

    def _detect_faces_by_skin_color(self, image) -> List[Tuple[int, int, int, int]]:
        """Fallback face detection using skin color"""
        try:
            # Convert to HSV
            hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
            
            # Define skin color range
            lower_skin = np.array([0, 20, 70], dtype=np.uint8)
            upper_skin = np.array([20, 255, 255], dtype=np.uint8)
            
            # Create mask
            mask = cv2.inRange(hsv, lower_skin, upper_skin)
            
            # Find contours
            contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            
            faces = []
            for contour in contours:
                area = cv2.contourArea(contour)
                if area > 1000:  # Minimum area threshold
                    x, y, w, h = cv2.boundingRect(contour)
                    if w > 30 and h > 30:  # Minimum size
                        faces.append((x, y, w, h))
            
            return faces
            
        except Exception as e:
            print(f"Error in skin color detection: {str(e)}", file=sys.stderr)
            return []

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
            
            # Extract face region
            face_region = image[y:y+h, x:x+w]
            
            # Resize to standard size
            face_region = cv2.resize(face_region, (64, 64))
            
            # Convert to grayscale
            gray_face = cv2.cvtColor(face_region, cv2.COLOR_BGR2GRAY)
            
            # Extract features
            features = []
            
            # 1. Histogram features
            hist = cv2.calcHist([gray_face], [0], None, [32], [0, 256])
            hist = hist.flatten() / np.sum(hist)
            features.extend(hist)
            
            # 2. Edge features
            edges = cv2.Canny(gray_face, 50, 150)
            edge_hist = cv2.calcHist([edges], [0], None, [16], [0, 256])
            edge_hist = edge_hist.flatten() / (np.sum(edge_hist) + 1e-8)
            features.extend(edge_hist)
            
            # 3. Local Binary Pattern approximation
            lbp_features = self._compute_lbp_features(gray_face)
            features.extend(lbp_features)
            
            # 4. Gradient features
            grad_x = cv2.Sobel(gray_face, cv2.CV_64F, 1, 0, ksize=3)
            grad_y = cv2.Sobel(gray_face, cv2.CV_64F, 0, 1, ksize=3)
            
            grad_magnitude = np.sqrt(grad_x**2 + grad_y**2)
            grad_direction = np.arctan2(grad_y, grad_x)
            
            # Gradient magnitude histogram
            mag_hist = np.histogram(grad_magnitude, bins=16, range=(0, np.max(grad_magnitude)))[0]
            mag_hist = mag_hist / (np.sum(mag_hist) + 1e-8)
            features.extend(mag_hist)
            
            # Gradient direction histogram
            dir_hist = np.histogram(grad_direction, bins=8, range=(-np.pi, np.pi))[0]
            dir_hist = dir_hist / (np.sum(dir_hist) + 1e-8)
            features.extend(dir_hist)
            
            # 5. Texture features
            texture_features = self._compute_texture_features(gray_face)
            features.extend(texture_features)
            
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

    def _compute_texture_features(self, gray_image: np.ndarray) -> List[float]:
        """Compute texture features"""
        features = []
        
        # Gray-level co-occurrence matrix approximation
        # Compute differences between adjacent pixels
        diff_h = np.diff(gray_image, axis=1)
        diff_v = np.diff(gray_image, axis=0)
        
        # Histogram of differences
        diff_h_hist = np.histogram(diff_h, bins=16, range=(-255, 255))[0]
        diff_v_hist = np.histogram(diff_v, bins=16, range=(-255, 255))[0]
        
        # Normalize
        diff_h_hist = diff_h_hist / (np.sum(diff_h_hist) + 1e-8)
        diff_v_hist = diff_v_hist / (np.sum(diff_v_hist) + 1e-8)
        
        features.extend(diff_h_hist)
        features.extend(diff_v_hist)
        
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
    Compare two face feature vectors
    Returns similarity score between 0 and 1
    """
    try:
        vec1 = np.array(face1_features)
        vec2 = np.array(face2_features)
        
        similarity = cosine_similarity(vec1, vec2)
        
        # Apply face-specific thresholding
        # Faces need higher similarity to be considered a match
        if similarity < 0.7:  # Lower threshold for face matching
            similarity = similarity * 0.5  # Penalize low similarities more
        
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