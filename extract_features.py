import os
import sys
import numpy as np
import warnings

# Suppress all warnings
warnings.filterwarnings('ignore')

# Set environment variables to avoid Windows issues
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
os.environ['TF_ENABLE_ONEDNN_OPTS'] = '0'
os.environ['TF_FORCE_GPU_ALLOW_GROWTH'] = 'true'

# Disable asyncio on Windows to avoid the service provider error
if os.name == 'nt':  # Windows
    os.environ['PYTHONASYNCIODEBUG'] = '0'
    # Disable multiprocessing to avoid asyncio issues
    os.environ['TF_DISABLE_MULTI_PROCESSING'] = '1'

# Suppress stdout for TensorFlow loading messages
import contextlib
import io

# Import TensorFlow with error handling
try:
    from tensorflow.keras.applications.mobilenet_v2 import MobileNetV2, preprocess_input
    from tensorflow.keras.preprocessing import image
    
    # Load the pre-trained model (suppress output during loading)
    with contextlib.redirect_stdout(io.StringIO()):
        model = MobileNetV2(weights='imagenet', include_top=False, pooling='avg')
        
except Exception as e:
    print(f"Error loading TensorFlow model: {str(e)}", file=sys.stderr)
    sys.exit(1)

def get_feature_vector(img_path):
    try:
        img = image.load_img(img_path, target_size=(224, 224))
        x = image.img_to_array(img)
        x = np.expand_dims(x, axis=0)
        x = preprocess_input(x)
        
        # Suppress prediction output
        with contextlib.redirect_stdout(io.StringIO()):
            features = model.predict(x, verbose=0)
        
        return features.flatten()
    except Exception as e:
        print(f"Error processing image {img_path}: {str(e)}", file=sys.stderr)
        raise

if __name__ == "__main__":
    import traceback
    if len(sys.argv) < 2:
        print("Usage: python extract_features.py <image_path>")
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