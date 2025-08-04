<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ImageController extends Controller
{
    public function showUploadForm()
    {
        return view('images.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('images', 'public');
            $fullPath = storage_path('app/public/' . $path);

            // Wait for file to be fully written
            $waits = 0;
            while ((!file_exists($fullPath) || filesize($fullPath) == 0) && $waits < 10) {
                usleep(200000); // wait 0.2s
                $waits++;
            }

            if (!@getimagesize($fullPath)) {
                return back()->with('error', 'Uploaded file is not a valid image or could not be processed.');
            }

            // Extract feature vector from the SAVED file
            $featureVector = $this->extractFeatureVector($fullPath);

            $image = new Image();
            $image->path = $path;
            $image->feature_vector = $featureVector;
            $image->save();

            return redirect()->back()->with('success', 'Image uploaded successfully!');
        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName() ?? 'unknown'
            ]);

            // Clean up the uploaded file if it exists
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }

            return back()->with('error', 'Failed to process image. Please try again with a different image.');
        }
    }

    public function showSearchForm()
    {
        return view('images.search');
    }


    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $file = $request->file('image');
        $path = $file->store('searches', 'public');
        $fullPath = storage_path('app/public/' . $path);

        // Wait for file to be fully written (sometimes needed on Windows)
        $waits = 0;
        while ((!file_exists($fullPath) || filesize($fullPath) == 0) && $waits < 10) {
            usleep(200000); // wait 0.2s
            $waits++;
        }

        // Check if file is a valid image
        if (!@getimagesize($fullPath)) {
            return back()->with('error', 'Uploaded file is not a valid image or could not be processed.');
        }

        // Extract feature vector from the SAVED file
        $searchVector = $this->extractFeatureVector($fullPath);
        $searchVectorArr = array_map('floatval', explode(',', $searchVector));

        // Validate search vector
        if (empty($searchVectorArr)) {
            return back()->with('error', 'Failed to extract features from uploaded image.');
        }

        $images = Image::whereNotNull('feature_vector')->get();
        $results = [];
        $allScores = [];
        foreach ($images as $img) {
            if (!$img->feature_vector) continue;

            try {
                $dbVectorArr = array_map('floatval', explode(',', $img->feature_vector));

                // Skip if database vector is empty
                if (empty($dbVectorArr)) {
                    Log::warning("Empty feature vector for image ID: {$img->id}");
                    continue;
                }

                // Debug: Log vector lengths and first few values
                Log::debug("Image ID {$img->id} - Search vector length: " . count($searchVectorArr) . ", DB vector length: " . count($dbVectorArr));
                if (count($searchVectorArr) > 0 && count($dbVectorArr) > 0) {
                    Log::debug("Search vector sample: " . implode(',', array_slice($searchVectorArr, 0, 5)));
                    Log::debug("DB vector sample: " . implode(',', array_slice($dbVectorArr, 0, 5)));
                }

                $similarity = $this->cosineSimilarity($searchVectorArr, $dbVectorArr);

                Log::info("Similarity with image ID {$img->id}: $similarity (threshold: 0.2)");
                
                // Debug category scores for the last 4 features
                if (count($searchVectorArr) >= 4 && count($dbVectorArr) >= 4) {
                    $search_flower = $searchVectorArr[count($searchVectorArr) - 4] ?? 0;
                    $search_animal = $searchVectorArr[count($searchVectorArr) - 3] ?? 0;
                    $search_jewelry = $searchVectorArr[count($searchVectorArr) - 2] ?? 0;
                    $search_human = $searchVectorArr[count($searchVectorArr) - 1] ?? 0;
                    
                    $db_flower = $dbVectorArr[count($dbVectorArr) - 4] ?? 0;
                    $db_animal = $dbVectorArr[count($dbVectorArr) - 3] ?? 0;
                    $db_jewelry = $dbVectorArr[count($dbVectorArr) - 2] ?? 0;
                    $db_human = $dbVectorArr[count($dbVectorArr) - 1] ?? 0;
                    
                    // Find dominant categories
                    $search_scores = [$search_flower, $search_animal, $search_jewelry, $search_human];
                    $search_max_index = array_search(max($search_scores), $search_scores);
                    $search_max_score = max($search_scores);
                    
                    $db_scores = [$db_flower, $db_animal, $db_jewelry, $db_human];
                    $db_max_index = array_search(max($db_scores), $db_scores);
                    $db_max_score = max($db_scores);
                    
                    $categories = ['FLOWER', 'ANIMAL', 'JEWELRY', 'HUMAN'];
                    
                    Log::info("Category analysis for image " . basename($img->path) . ":");
                    Log::info("  Search: F=" . number_format($search_flower, 3) . ", A=" . number_format($search_animal, 3) . ", J=" . number_format($search_jewelry, 3) . ", H=" . number_format($search_human, 3));
                    Log::info("  DB:     F=" . number_format($db_flower, 3) . ", A=" . number_format($db_animal, 3) . ", J=" . number_format($db_jewelry, 3) . ", H=" . number_format($db_human, 3));
                    Log::info("  Search dominant: " . $categories[$search_max_index] . " (" . number_format($search_max_score, 2) . ")");
                    Log::info("  DB dominant: " . $categories[$db_max_index] . " (" . number_format($db_max_score, 2) . ")");
                }

                $allScores[] = [
                    'image' => $img,
                    'similarity' => $similarity,
                ];

                // Add images to results with lower similarity threshold
                if ($similarity >= 0.1) {
                    $results[] = [
                        'image' => $img,
                        'similarity' => $similarity,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error processing image ID {$img->id}: " . $e->getMessage());
                continue;
            }
        }
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Also sort allScores for consistent display
        usort($allScores, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Log summary for debugging
        Log::info("Search completed: " . count($allScores) . " images processed, " . count($results) . " above threshold (0.1)");
        if (count($allScores) > 0) {
            $maxSimilarity = max(array_column($allScores, 'similarity'));
            $avgSimilarity = array_sum(array_column($allScores, 'similarity')) / count($allScores);
            Log::info("Similarity stats - Max: $maxSimilarity, Avg: $avgSimilarity");
            
            // Log top 5 matches for debugging
            $topMatches = array_slice($allScores, 0, 5);
            foreach ($topMatches as $match) {
                Log::info("Top match: " . basename($match['image']->path) . " - Similarity: " . number_format($match['similarity'], 3));
            }
        }

        return view('images.results', ['results' => $results, 'allScores' => $allScores]);
    }


    // Helper: Call Python script
    private function extractFeatureVector($imagePath)
    {
        // Use the simple PIL-based script directly since TensorFlow has Windows issues
        return $this->extractFeatureVectorFallback($imagePath);
    }

    // Method using PIL-based image processing (works reliably on Windows)
    private function extractFeatureVectorFallback($imagePath)
    {
        $pythonPath = 'python';
        $scriptPath = base_path('extract_features_simple.py');

        $process = new Process([$pythonPath, $scriptPath, $imagePath]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(30); // Shorter timeout for simpler script

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('PIL-based Python script failed', [
                    'command' => $process->getCommandLine(),
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput(),
                    'exit_code' => $process->getExitCode()
                ]);
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());

            // Validate that we got a proper feature vector
            if (empty($output) || !str_contains($output, ',')) {
                Log::error('Invalid feature vector output from PIL method', ['output' => $output]);
                throw new \Exception('Failed to extract valid feature vector from image using PIL method');
            }

            Log::info('Successfully extracted features using PIL-based method');
            return $output;
        } catch (\Exception $e) {
            Log::error('Exception in PIL-based extractFeatureVector', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // Helper: Cosine similarity
    private function cosineSimilarity($vec1, $vec2)
    {
        // Ensure both vectors have the same length
        $len1 = count($vec1);
        $len2 = count($vec2);

        if ($len1 !== $len2) {
            Log::warning("Feature vector length mismatch: vec1={$len1}, vec2={$len2}");

            // For better compatibility, we'll use the first 1280 features if available
            // This matches the TensorFlow MobileNetV2 output size
            $targetLength = 1280;

            if ($len1 > $targetLength) {
                $vec1 = array_slice($vec1, 0, $targetLength);
            }
            if ($len2 > $targetLength) {
                $vec2 = array_slice($vec2, 0, $targetLength);
            }

            // If either vector is shorter than target, pad with zeros
            if ($len1 < $targetLength) {
                $vec1 = array_pad($vec1, $targetLength, 0.0);
            }
            if ($len2 < $targetLength) {
                $vec2 = array_pad($vec2, $targetLength, 0.0);
            }
        }

        // Normalize vectors to unit length for better comparison
        $norm1 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vec1)));
        $norm2 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vec2)));
        
        if ($norm1 > 0) {
            $vec1 = array_map(function($x) use ($norm1) { return $x / $norm1; }, $vec1);
        }
        if ($norm2 > 0) {
            $vec2 = array_map(function($x) use ($norm2) { return $x / $norm2; }, $vec2);
        }

        $dot = 0.0;
        for ($i = 0; $i < count($vec1); $i++) {
            $dot += $vec1[$i] * $vec2[$i];
        }

        // Cosine similarity is already between -1 and 1, convert to 0-1 range
        $similarity = ($dot + 1) / 2;

        // Apply a more lenient transformation to allow more matches
        // This makes the similarity scores less strict
        $similarity = max(0, min(1, $similarity * 1.2));

        // COMPLETE CATEGORY BLOCKING - only allow same category matches
        // Check if both images have similar category scores (last 4 features)
        if (count($vec1) >= 4 && count($vec2) >= 4) {
            $search_flower = $vec1[count($vec1) - 4] ?? 0;
            $search_animal = $vec1[count($vec1) - 3] ?? 0;
            $search_jewelry = $vec1[count($vec1) - 2] ?? 0;
            $search_human = $vec1[count($vec1) - 1] ?? 0;
            
            $db_flower = $vec2[count($vec2) - 4] ?? 0;
            $db_animal = $vec2[count($vec2) - 3] ?? 0;
            $db_jewelry = $vec2[count($vec2) - 2] ?? 0;
            $db_human = $vec2[count($vec2) - 1] ?? 0;
            
            // Find dominant category for search image
            $search_scores = [$search_flower, $search_animal, $search_jewelry, $search_human];
            $search_max_index = array_search(max($search_scores), $search_scores);
            
            // Find dominant category for database image
            $db_scores = [$db_flower, $db_animal, $db_jewelry, $db_human];
            $db_max_index = array_search(max($db_scores), $db_scores);
            
            $categories = ['FLOWER', 'ANIMAL', 'JEWELRY', 'HUMAN'];
            
            // IMPROVED CATEGORY BLOCKING - only allow same category matches with minimum confidence
            $search_max_score = max($search_scores);
            $db_max_score = max($db_scores);
            
            if ($search_max_index === $db_max_index && $search_max_score > 0.01 && $db_max_score > 0.01) {
                // Same category - MASSIVE BOOST
                $similarity *= 5.0; // 5x boost for same category
                Log::info($categories[$search_max_index] . "-" . $categories[$db_max_index] . " MATCH - MASSIVE BOOST (scores: " . number_format($search_max_score, 2) . ", " . number_format($db_max_score, 2) . ")");
            } else if ($search_max_score > 0.02 && $db_max_score > 0.02) {
                // Different categories - COMPLETE BLOCK
                $similarity = 0.0; // Completely block cross-category matches
                Log::info($categories[$search_max_index] . "-" . $categories[$db_max_index] . " MATCH - COMPLETELY BLOCKED (scores: " . number_format($search_max_score, 2) . ", " . number_format($db_max_score, 2) . ")");
            } else {
                // Very low confidence - allow normal matching
                Log::info($categories[$search_max_index] . "-" . $categories[$db_max_index] . " MATCH - Normal matching (very low confidence)");
            }
        }

        return $similarity;
    }
}
