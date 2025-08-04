<?php

require_once 'vendor/autoload.php';

use App\Models\Image;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking images in database...\n\n";

try {
    $images = Image::all();
    
    if ($images->count() == 0) {
        echo "No images found in database.\n";
        exit(0);
    }
    
    echo "Found " . $images->count() . " images in database:\n\n";
    
    foreach ($images as $image) {
        $fullPath = storage_path('app/public/' . $image->path);
        $exists = file_exists($fullPath) ? "EXISTS" : "MISSING";
        $size = file_exists($fullPath) ? filesize($fullPath) : 0;
        
        echo "ID: " . $image->id . "\n";
        echo "Path: " . $image->path . "\n";
        echo "Full Path: " . $fullPath . "\n";
        echo "Status: " . $exists . "\n";
        echo "Size: " . $size . " bytes\n";
        echo "Feature Vector: " . (empty($image->feature_vector) ? "EMPTY" : "EXISTS (" . strlen($image->feature_vector) . " chars)") . "\n";
        echo "---\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 