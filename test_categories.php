<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing category detection for all images:\n";
echo "==========================================\n\n";

try {
    $images = \App\Models\Image::whereNotNull('feature_vector')->get();
    
    foreach ($images as $img) {
        $vec = array_map('floatval', explode(',', $img->feature_vector));
        
        if (count($vec) >= 6) {
            $categories = ['FLOWER', 'ANIMAL', 'JEWELRY', 'HUMAN'];
            $scores = array_slice($vec, -6, 4); // First 4 are main categories
            $rabbit_score = $vec[count($vec) - 2] ?? 0;
            $deer_score = $vec[count($vec) - 1] ?? 0;
            
            $max_index = array_search(max($scores), $scores);
            $max_score = max($scores);
            
            echo basename($img->path) . ":\n";
            echo "  Main Category: " . $categories[$max_index] . " (" . number_format($max_score, 3) . ")\n";
            echo "  All scores: F=" . number_format($scores[0], 3) . 
                 ", A=" . number_format($scores[1], 3) . 
                 ", J=" . number_format($scores[2], 3) . 
                 ", H=" . number_format($scores[3], 3) . "\n";
            
            if ($max_index === 1) { // ANIMAL category
                echo "  Animal subtypes: Rabbit=" . number_format($rabbit_score, 3) . 
                     ", Deer=" . number_format($deer_score, 3) . "\n";
                
                if ($rabbit_score > $deer_score && $rabbit_score > 0.4) {
                    echo "  → Detected as: RABBIT\n";
                } elseif ($deer_score > $rabbit_score && $deer_score > 0.4) {
                    echo "  → Detected as: DEER\n";
                } else {
                    echo "  → Detected as: ANIMAL (generic)\n";
                }
            } else {
                echo "  → Detected as: " . $categories[$max_index] . "\n";
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 