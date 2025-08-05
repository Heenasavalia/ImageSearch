<?php

/**
 * Simple script to add images to the face search database
 * 
 * Usage:
 * 1. Place your images in the storage/app/public/images/ folder
 * 2. Run this script: php add_images.php
 * 3. The script will process all images and extract face features
 */

require_once 'vendor/autoload.php';

use App\Models\Image;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Face Search - Image Database Builder\n";
echo "=====================================\n\n";

// Get all images from the storage/app/public/images/ directory
$imagesPath = storage_path('app/public/images/');
if (!is_dir($imagesPath)) {
    echo "❌ Images directory not found: {$imagesPath}\n";
    echo "Please create the directory and add your images there.\n";
    exit(1);
}

$imageFiles = glob($imagesPath . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($imageFiles)) {
    echo "❌ No images found in {$imagesPath}\n";
    echo "Please add some images (JPG, PNG, GIF) to the directory.\n";
    exit(1);
}

echo "📁 Found " . count($imageFiles) . " images to process\n\n";

$processed = 0;
$successful = 0;
$failed = 0;

foreach ($imageFiles as $imagePath) {
    $filename = basename($imagePath);
    echo "Processing: {$filename}... ";
    
    try {
        // Check if image already exists in database
        $relativePath = 'images/' . $filename;
        $existingImage = Image::where('path', $relativePath)->first();
        
        if ($existingImage) {
            echo "⏭️  Already exists in database\n";
            $processed++;
            continue;
        }
        
        // Extract face features using the face detection script
        $faceData = extractFaceFeatures($imagePath);
        
        if (!$faceData['has_faces']) {
            echo "❌ No faces detected\n";
            $failed++;
            continue;
        }
        
        // Save to database
        $image = new Image();
        $image->path = $relativePath;
        $image->has_faces = $faceData['has_faces'];
        $image->face_count = $faceData['face_count'];
        $image->face_features = $faceData['face_features'];
        $image->face_rectangles = $faceData['face_rectangles'];
        $image->save();
        
        echo "✅ Added ({$faceData['face_count']} face(s))\n";
        $successful++;
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
    
    $processed++;
}

echo "\n📊 Summary:\n";
echo "Processed: {$processed}\n";
echo "Successful: {$successful}\n";
echo "Failed: {$failed}\n";
echo "Total images in database: " . Image::where('has_faces', true)->count() . "\n";

if ($successful > 0) {
    echo "\n🎉 Database updated successfully! You can now use the face search.\n";
} else {
    echo "\n⚠️  No new images were added. Make sure your images contain human faces.\n";
}

/**
 * Extract face features from image using the face detection script
 */
function extractFaceFeatures($imagePath) {
    $pythonPath = 'python';
    $scriptPath = base_path('face_detection.py');

    $process = new Process([$pythonPath, $scriptPath, $imagePath]);
    $process->setWorkingDirectory(base_path());
    $process->setTimeout(60);

    try {
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Face detection failed: ' . $process->getErrorOutput());
        }

        $output = trim($process->getOutput());
        $lines = explode("\n", $output);

        $faceData = [
            'has_faces' => false,
            'face_count' => 0,
            'face_features' => [],
            'face_rectangles' => []
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'FACES_DETECTED:') === 0) {
                $faceData['has_faces'] = true;
                $faceData['face_count'] = (int) substr($line, 15);
            } elseif (strpos($line, 'FACE_') === 0) {
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $faceInfo = explode('|', $parts[1], 2);
                    if (count($faceInfo) == 2) {
                        $rectStr = $faceInfo[0];
                        $featuresStr = $faceInfo[1];
                        
                        // Parse rectangle
                        $rectParts = explode(',', $rectStr);
                        if (count($rectParts) == 4) {
                            $rectangle = array_map('intval', $rectParts);
                            $faceData['face_rectangles'][] = $rectangle;
                        }
                        
                        // Parse features
                        $features = array_map('floatval', explode(',', $featuresStr));
                        $faceData['face_features'][] = $features;
                    }
                }
            }
        }

        return $faceData;

    } catch (Exception $e) {
        throw new Exception('Face detection error: ' . $e->getMessage());
    }
} 