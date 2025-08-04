<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Image Search Engine

A Laravel-based image search engine that uses feature extraction and similarity matching to find similar images.

## Recent Fixes (Latest Update)

### Problem Fixed
The system was returning 100% similarity for all images regardless of their actual similarity to the search query. This was caused by:
1. Incorrect similarity calculation in the `cosineSimilarity` function
2. Poor category filtering that allowed cross-category matches (e.g., rabbits matching with flowers)
3. Overly lenient similarity thresholds

### Solutions Implemented

#### 1. Fixed Similarity Calculation
- **Removed artificial boosts**: Eliminated the 5x multiplier that was inflating similarity scores
- **Proper cosine similarity**: Implemented correct cosine similarity calculation using normalized vectors
- **Category filtering**: Added strict category-based filtering to prevent cross-category matches

#### 2. Improved Category Detection
- **Better thresholds**: Updated category detection thresholds for more accurate classification
- **Enhanced features**: Improved feature extraction for flowers, animals, jewelry, and humans
- **Strict filtering**: Only allows matches within the same category when confidence is high

#### 3. Updated Similarity Thresholds
- **Higher threshold**: Increased minimum similarity from 10% to 30% for better accuracy
- **Better logging**: Added detailed logging to help debug similarity calculations

## How to Use

### 1. Upload Images
- Navigate to the upload page
- Upload images to build your search database
- Images are automatically processed to extract features

### 2. Search for Similar Images
- Go to the search page
- Upload an image to search for similar images
- Results will show only images with 30%+ similarity
- Cross-category matches are blocked (e.g., flowers won't match animals)

### 3. Re-extract Features (if needed)
- If you have existing images with old feature vectors, click "Re-extract Features"
- This will reprocess all images with the improved algorithm
- Use this after updating the system to ensure all images use the new detection

## Technical Details

### Feature Extraction
The system uses a Python script (`extract_features_simple.py`) that extracts:
- Color analysis (RGB channels, saturation, dominance)
- Texture analysis (gradients, edge density)
- Shape analysis (area ratios, binary patterns)
- Category-specific features for flowers, animals, jewelry, and humans

### Similarity Calculation
- Uses cosine similarity between normalized feature vectors
- Excludes category scores from main similarity calculation
- Applies category filtering to prevent cross-category matches
- Returns similarity scores between 0 and 1

### Category Detection
The system classifies images into four categories:
- **Flowers**: High saturation, red dominance, green backgrounds
- **Animals**: High texture, brown/gray colors, fur patterns
- **Jewelry**: High brightness, smooth surfaces, limited colors
- **Humans**: Moderate brightness, smooth skin, specific color ranges

## Requirements

- PHP 8.0+
- Laravel 10+
- Python 3.7+ with PIL/Pillow
- MySQL/PostgreSQL database

## Installation

1. Clone the repository
2. Install PHP dependencies: `composer install`
3. Install Python dependencies: `pip install pillow numpy`
4. Configure your database in `.env`
5. Run migrations: `php artisan migrate`
6. Start the server: `php artisan serve`

## Testing

To test the feature extraction:
```bash
python test_features.py
```

This will analyze a test image and show category scores to verify the system is working correctly.

## Troubleshooting

### All images showing 100% similarity
1. Re-extract features for existing images using the "Re-extract Features" button
2. Check the logs for detailed similarity calculations
3. Verify that images are being properly categorized

### Poor search results
1. Ensure you have enough images in your database
2. Try uploading more diverse images
3. Check that images are in the same category as your search query

### Feature extraction errors
1. Verify Python and PIL are installed correctly
2. Check that image files are valid and accessible
3. Review the Laravel logs for detailed error messages
