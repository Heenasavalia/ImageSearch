<?php

require_once 'vendor/autoload.php';

use App\Models\Image;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Re-extracting features with subtype detection...\n";

try {
    $images = Image::whereNotNull('path')->get();
    $updated = 0;
    
    foreach ($images as $image) {
        $fullPath = storage_path('app/public/' . $image->path);
        
        if (file_exists($fullPath)) {
            // Call Python script directly
            $pythonPath = 'python';
            $scriptPath = base_path('extract_features_simple.py');
            
            $process = new Symfony\Component\Process\Process([$pythonPath, $scriptPath, $fullPath]);
            $process->setWorkingDirectory(base_path());
            $process->setTimeout(30);
            
            $process->run();
            
            if ($process->isSuccessful()) {
                $newFeatureVector = trim($process->getOutput());
                $image->feature_vector = $newFeatureVector;
                $image->save();
                $updated++;
                
                echo "Updated: " . basename($image->path) . "\n";
            } else {
                echo "Failed: " . basename($image->path) . " - " . $process->getErrorOutput() . "\n";
            }
        }
    }
    
    echo "Successfully updated {$updated} images with subtype detection.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 