# Face Search System

A simple Laravel-based face search system that finds similar human faces in your image database.

## Features

- 🔍 **Face-only search**: Only matches human faces, ignores animals, objects, etc.
- 👤 **Face detection**: Automatically detects and extracts face features
- 📊 **Similarity scoring**: Shows percentage match for each result
- 🎯 **Accurate matching**: Uses advanced face recognition algorithms
- 📤 **Easy upload**: Web interface to upload face images to database

## Requirements

- PHP 8.0+
- Laravel 10+
- Python 3.7+ with required packages (see below)
- MySQL/PostgreSQL database

## Installation

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Install Python dependencies:**
   ```bash
   pip install opencv-python face-recognition numpy pillow
   ```

3. **Set up environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=face_search
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Create storage link:**
   ```bash
   php artisan storage:link
   ```

## Usage

### 1. Upload Face Images to Database

1. Start the Laravel server:
   ```bash
   php artisan serve
   ```

2. Open your browser to `http://localhost:8000`

3. You'll be redirected to the upload page

4. Upload images containing human faces:
   - Only images with detected faces will be saved
   - Images without faces will be rejected
   - Multiple faces in one image are supported

### 2. Search for Similar Faces

1. Click "Search Faces" or go to `http://localhost:8000/images/search`

2. Upload an image containing a human face to search

3. View the search results showing similar faces with match percentages

## How It Works

1. **Upload Process**: 
   - Images are uploaded via web interface
   - Face detection runs automatically
   - Only images with faces are saved to database
   - Face features are extracted and stored

2. **Search Process**:
   - Upload a search image with a face
   - System detects faces in search image
   - Compares with all faces in database
   - Shows results sorted by similarity

3. **Face Detection**: Uses OpenCV and face_recognition libraries
4. **Feature Extraction**: Extracts 128-dimensional face embeddings
5. **Similarity Calculation**: Compares face embeddings using cosine similarity

## File Structure

```
├── app/
│   ├── Http/Controllers/
│   │   └── ImageController.php      # Main controller for upload and search
│   └── Models/
│       └── Image.php                # Image model with face data
├── resources/views/images/
│   ├── upload.blade.php             # Upload interface
│   ├── search.blade.php             # Search interface
│   └── results.blade.php            # Results display
├── face_detection.py                # Python script for face detection
├── add_images.php                   # Alternative script to add images
├── setup.php                        # Setup helper script
└── routes/web.php                   # Application routes
```

## Database Schema

The `images` table stores:
- `path`: Image file path
- `has_faces`: Boolean indicating if faces were detected
- `face_count`: Number of faces detected
- `face_features`: Array of face embeddings (128-dimensional vectors)
- `face_rectangles`: Array of face bounding boxes

## API Endpoints

- `GET /` - Redirects to upload form
- `GET /images/upload` - Show upload form
- `POST /images/upload` - Process image upload
- `GET /images/search` - Show search form
- `POST /images/search` - Process search and show results

## Alternative: Command Line Upload

If you prefer to add images via command line:

1. Place images in `storage/app/public/images/`
2. Run: `php add_images.php`

## Troubleshooting

### No faces detected
- Ensure images contain clear, front-facing human faces
- Check that images are not too small or blurry
- Verify Python face detection libraries are installed correctly

### Search returns no results
- Make sure you have images with faces in your database
- Try uploading a different face image for search
- Check that the face detection script is working properly

### Images not displaying
- Ensure storage link is created: `php artisan storage:link`
- Check file permissions on storage directory
- Verify images are being saved correctly

### Python script errors
- Verify Python 3.7+ is installed
- Install required packages: `pip install opencv-python face-recognition numpy pillow`
- Check that the `face_detection.py` script is executable

## Security Notes

- Only human face images are processed and stored
- No general image features are extracted
- Face data is stored locally in your database
- No external API calls for face recognition

## License

This project is open source and available under the MIT License.
