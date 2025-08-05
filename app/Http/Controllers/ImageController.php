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
            'image' => 'required|image|max:10240', // Increased to 10MB
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

            // Extract face features
            $faceData = $this->extractFaceFeatures($fullPath);

            $image = new Image();
            $image->path = $path;
            $image->feature_vector = $featureVector;
            $image->has_faces = $faceData['has_faces'];
            $image->face_count = $faceData['face_count'];
            $image->face_features = $faceData['face_features'];
            $image->face_rectangles = $faceData['face_rectangles'];
            $image->save();

            $message = 'Image uploaded successfully!';
            if ($faceData['has_faces']) {
                $message .= ' Found ' . $faceData['face_count'] . ' face(s).';
            }

            return redirect()->back()->with('success', $message);
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

    public function showFaceSearchForm()
    {
        return view('images.face-search');
    }

    public function reExtractFeatures()
    {
        try {
            $images = Image::whereNotNull('path')->get();
            $updated = 0;
            
            foreach ($images as $image) {
                $fullPath = storage_path('app/public/' . $image->path);
                
                if (file_exists($fullPath)) {
                    $newFeatureVector = $this->extractFeatureVector($fullPath);
                    $image->feature_vector = $newFeatureVector;
                    $image->save();
                    $updated++;
                    Log::info("Re-extracted features for image: " . basename($image->path));
                }
            }
            
            return redirect()->back()->with('success', "Successfully re-extracted features for {$updated} images.");
        } catch (\Exception $e) {
            Log::error('Feature re-extraction failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to re-extract features: ' . $e->getMessage());
        }
    }

    public function testSimilarity()
    {
        try {
            $images = Image::whereNotNull('feature_vector')->take(5)->get();
            
            if ($images->count() < 2) {
                return response()->json(['error' => 'Need at least 2 images to test similarity']);
            }
            
            $results = [];
            
            // Test similarity between first two images
            $img1 = $images[0];
            $img2 = $images[1];
            
            $vec1 = array_map('floatval', explode(',', $img1->feature_vector));
            $vec2 = array_map('floatval', explode(',', $img2->feature_vector));
            
            $similarity = $this->calculateVisualSimilarity($vec1, $vec2);
            
            $results = [
                'image1' => basename($img1->path),
                'image2' => basename($img2->path),
                'similarity' => $similarity,
                'vector_length1' => count($vec1),
                'vector_length2' => count($vec2),
            ];
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function debugFeatures()
    {
        try {
            $images = Image::whereNotNull('feature_vector')->get();
            
            if ($images->count() == 0) {
                return response()->json(['error' => 'No images found']);
            }
            
            $results = [];
            
            foreach ($images as $img) {
                $vec = array_map('floatval', explode(',', $img->feature_vector));
                
                $results[] = [
                    'image' => basename($img->path),
                    'vector_length' => count($vec),
                    'mean_value' => number_format(array_sum($vec) / count($vec), 3),
                    'std_value' => number_format(sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vec)) / count($vec)), 3)
                ];
            }
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }


    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // Increased to 10MB
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

        // Extract feature vector from the uploaded image
        $searchVector = $this->extractFeatureVector($fullPath);
        $searchVectorArr = array_map('floatval', explode(',', $searchVector));

        // Extract face features from the uploaded image
        $searchFaceData = $this->extractFaceFeatures($fullPath);

        // Validate search vector
        if (empty($searchVectorArr)) {
            return back()->with('error', 'Failed to extract features from uploaded image.');
        }

        Log::info("Search image features extracted successfully. Vector length: " . count($searchVectorArr));
        Log::info("Search image face detection: " . ($searchFaceData['has_faces'] ? "Found " . $searchFaceData['face_count'] . " face(s)" : "No faces detected"));

        // Determine the category of the search image
        $searchCategory = $this->determineImageCategory($searchVectorArr);
        Log::info("Search image category: " . $searchCategory['name'] . " (confidence: " . number_format($searchCategory['confidence'], 3) . ")");

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

                // Determine the category of the database image
                $dbCategory = $this->determineImageCategory($dbVectorArr);
                
                // Calculate visual similarity
                $visualSimilarity = $this->calculateVisualSimilarity($searchVectorArr, $dbVectorArr);
                
                // Calculate face similarity if both images have faces
                $faceSimilarity = 0.0;
                $hasFaceMatch = false;
                
                if ($searchFaceData['has_faces'] && $img->has_faces && !empty($img->face_features)) {
                    $faceSimilarity = $this->calculateFaceSimilarity($searchFaceData, $img);
                    $hasFaceMatch = $faceSimilarity > 0.6; // Face match threshold
                }

                // Combine similarities - prioritize face matching for human photos
                $finalSimilarity = $visualSimilarity;
                if ($hasFaceMatch) {
                    // If faces are detected and match, boost the similarity
                    $finalSimilarity = max($visualSimilarity, $faceSimilarity * 1.2);
                    Log::info("Face match found! Visual: " . number_format($visualSimilarity, 3) . 
                             ", Face: " . number_format($faceSimilarity, 3) . 
                             ", Final: " . number_format($finalSimilarity, 3) . 
                             " for " . basename($img->path));
                }

                $allScores[] = [
                    'image' => $img,
                    'similarity' => $finalSimilarity,
                    'category' => $dbCategory['name'],
                    'visual_similarity' => $visualSimilarity,
                    'face_similarity' => $faceSimilarity,
                    'has_face_match' => $hasFaceMatch
                ];

                // Include results with high similarity (lowered threshold for face matching)
                $threshold = $hasFaceMatch ? 0.75 : 0.85; // Lower threshold when faces match
                if ($finalSimilarity >= $threshold) {
                    $results[] = [
                        'image' => $img,
                        'similarity' => $finalSimilarity,
                        'category' => $dbCategory['name'],
                        'visual_similarity' => $visualSimilarity,
                        'face_similarity' => $faceSimilarity,
                        'has_face_match' => $hasFaceMatch
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error processing image ID {$img->id}: " . $e->getMessage());
                continue;
            }
        }

        // Sort results by similarity (highest first)
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        usort($allScores, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Log summary
        Log::info("Search completed: " . count($allScores) . " images processed, " . count($results) . " above threshold");
        
        if (count($results) == 0) {
            Log::info("No matches found");
        }

        return view('images.results', [
            'results' => $results, 
            'allScores' => $allScores,
            'searchCategory' => $searchCategory['name'],
            'searchFaceData' => $searchFaceData
        ]);
    }

    public function faceSearch(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $file = $request->file('image');
        $path = $file->store('searches', 'public');
        $fullPath = storage_path('app/public/' . $path);

        // Wait for file to be fully written
        $waits = 0;
        while ((!file_exists($fullPath) || filesize($fullPath) == 0) && $waits < 10) {
            usleep(200000);
            $waits++;
        }

        if (!@getimagesize($fullPath)) {
            return back()->with('error', 'Uploaded file is not a valid image or could not be processed.');
        }

        // Extract face features from the uploaded image
        $searchFaceData = $this->extractFaceFeatures($fullPath);

        if (!$searchFaceData['has_faces']) {
            return back()->with('error', 'No faces detected in the uploaded image. Please upload an image with a clear face.');
        }

        Log::info("Face search: Found " . $searchFaceData['face_count'] . " face(s) in search image");

        // Get all images with faces
        $images = Image::where('has_faces', true)
                      ->whereNotNull('face_features')
                      ->get();

        $results = [];
        $allScores = [];
        
        foreach ($images as $img) {
            try {
                // Calculate face similarity
                $faceSimilarity = $this->calculateFaceSimilarity($searchFaceData, $img);
                
                if ($faceSimilarity > 0.5) { // Lower threshold for face-only search
                    $results[] = [
                        'image' => $img,
                        'similarity' => $faceSimilarity,
                        'face_similarity' => $faceSimilarity,
                        'has_face_match' => true
                    ];
                }

                $allScores[] = [
                    'image' => $img,
                    'similarity' => $faceSimilarity,
                    'face_similarity' => $faceSimilarity,
                    'has_face_match' => $faceSimilarity > 0.5
                ];

            } catch (\Exception $e) {
                Log::error("Error processing image ID {$img->id}: " . $e->getMessage());
                continue;
            }
        }

        // Sort results by face similarity (highest first)
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        usort($allScores, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        Log::info("Face search completed: " . count($allScores) . " images processed, " . count($results) . " face matches found");

        return view('images.face-results', [
            'results' => $results,
            'allScores' => $allScores,
            'searchFaceData' => $searchFaceData
        ]);
    }

    // NEW: Determine image category with confidence score
    private function determineImageCategory($vector)
    {
        if (count($vector) < 160) {
            return ['name' => 'UNKNOWN', 'confidence' => 0.0];
        }

        // Extract key features for category detection
        $dominant_colors = array_slice($vector, 0, 30);
        $color_features = array_slice($vector, 30, 20);
        $texture_features = array_slice($vector, 50, 25);
        $shape_features = array_slice($vector, 75, 15);
        $lighting_features = array_slice($vector, 90, 10);
        $edge_features = array_slice($vector, 100, 20);
        $spatial_features = array_slice($vector, 120, 40);

        // Calculate category scores based on feature analysis
        $flower_score = $this->calculateFlowerScore($dominant_colors, $color_features, $texture_features, $shape_features, $lighting_features);
        $animal_score = $this->calculateAnimalScore($dominant_colors, $texture_features, $shape_features, $lighting_features, $edge_features);
        $jewelry_score = $this->calculateJewelryScore($dominant_colors, $lighting_features, $edge_features, $spatial_features);

        $scores = [
            'FLOWER' => $flower_score,
            'ANIMAL' => $animal_score,
            'JEWELRY' => $jewelry_score
        ];

        // Find the category with the highest score
        $maxCategory = array_search(max($scores), $scores);
        $maxScore = max($scores);

        return ['name' => $maxCategory, 'confidence' => $maxScore];
    }

    private function calculateFlowerScore($dominant_colors, $color_features, $texture_features, $shape_features, $lighting_features)
    {
        $score = 0.0;
        
        // Flowers typically have:
        // 1. High color saturation and variety
        $color_variety = array_sum(array_slice($color_features, 0, 12)); // Color variance features
        if ($color_variety > 0.5) $score += 0.3;
        
        // 2. Smooth texture (low texture complexity)
        $texture_complexity = $texture_features[0] + $texture_features[1]; // Horizontal and vertical texture
        if ($texture_complexity < 0.2) $score += 0.3;
        
        // 3. Centered, symmetrical shapes
        $symmetry = $shape_features[3] ?? 0; // Symmetry score
        if ($symmetry > 0.6) $score += 0.2;
        
        // 4. Natural lighting (not too bright or uniform)
        $brightness_uniformity = $lighting_features[5] ?? 0; // Brightness uniformity
        if ($brightness_uniformity > 0.4 && $brightness_uniformity < 0.8) $score += 0.2;
        
        return min(1.0, $score);
    }

    private function calculateAnimalScore($dominant_colors, $texture_features, $shape_features, $lighting_features, $edge_features)
    {
        $score = 0.0;
        
        // Animals typically have:
        // 1. High texture complexity (fur)
        $texture_complexity = $texture_features[0] + $texture_features[1];
        if ($texture_complexity > 0.3) $score += 0.3;
        
        // 2. Many edges and details
        $edge_density = $edge_features[5] ?? 0; // Edge density
        if ($edge_density > 0.2) $score += 0.3;
        
        // 3. Natural colors (browns, grays)
        $color_variety = array_sum(array_slice($dominant_colors, 0, 10));
        if ($color_variety < 0.4) $score += 0.2;
        
        // 4. Varied lighting (natural outdoor lighting)
        $brightness_std = $lighting_features[1] ?? 0; // Brightness standard deviation
        if ($brightness_std > 0.2) $score += 0.2;
        
        return min(1.0, $score);
    }

    private function calculateJewelryScore($dominant_colors, $lighting_features, $edge_features, $spatial_features)
    {
        $score = 0.0;
        
        // Jewelry typically has:
        // 1. Very bright and uniform lighting
        $brightness = $lighting_features[0] ?? 0; // Average brightness
        $uniformity = $lighting_features[5] ?? 0; // Brightness uniformity
        if ($brightness > 0.7) $score += 0.3;
        if ($uniformity > 0.8) $score += 0.2;
        
        // 2. Sharp edges and high contrast
        $edge_strength = $edge_features[2] ?? 0; // Max edge magnitude
        if ($edge_strength > 0.5) $score += 0.3;
        
        // 3. Metallic colors (silver, gold tones)
        $color_uniformity = array_sum(array_slice($spatial_features, 0, 16)) / 16; // Average color uniformity
        if ($color_uniformity > 0.6) $score += 0.2;
        
        return min(1.0, $score);
    }

    // NEW: Calculate visual similarity using proper feature comparison
    private function calculateVisualSimilarity($vec1, $vec2)
    {
        // Ensure both vectors have the same length
        $len1 = count($vec1);
        $len2 = count($vec2);

        if ($len1 !== $len2) {
            Log::warning("Feature vector length mismatch: vec1={$len1}, vec2={$len2}");
            $targetLength = min($len1, $len2);
            $vec1 = array_slice($vec1, 0, $targetLength);
            $vec2 = array_slice($vec2, 0, $targetLength);
        }

        // Calculate cosine similarity
        $dotProduct = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $mag1 += $vec1[$i] * $vec1[$i];
            $mag2 += $vec2[$i] * $vec2[$i];
        }
        
        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);
        
        if ($mag1 <= 0 || $mag2 <= 0) {
            return 0.0;
        }
        
        $cosineSimilarity = $dotProduct / ($mag1 * $mag2);
        
        // Convert from [-1, 1] range to [0, 1] range
        $similarity = ($cosineSimilarity + 1) / 2;
        
        // Apply very strict scaling for exact matching
        // This ensures that only very similar images get high scores
        $similarity = $similarity * $similarity * $similarity; // Cube to make it very strict
        
        return $similarity;
    }

    // Calculate face similarity between two images
    private function calculateFaceSimilarity($searchFaceData, $dbImage)
    {
        try {
            if (!$searchFaceData['has_faces'] || !$dbImage->has_faces || empty($dbImage->face_features)) {
                return 0.0;
            }

            $maxSimilarity = 0.0;

            // Compare each face in search image with each face in database image
            foreach ($searchFaceData['face_features'] as $searchFaceFeatures) {
                foreach ($dbImage->face_features as $dbFaceFeatures) {
                    $similarity = $this->calculateFaceFeatureSimilarity($searchFaceFeatures, $dbFaceFeatures);
                    $maxSimilarity = max($maxSimilarity, $similarity);
                }
            }

            return $maxSimilarity;

        } catch (\Exception $e) {
            Log::error("Error calculating face similarity: " . $e->getMessage());
            return 0.0;
        }
    }

    // Calculate similarity between two face feature vectors
    private function calculateFaceFeatureSimilarity($face1Features, $face2Features)
    {
        try {
            // Ensure both vectors have the same length
            $len1 = count($face1Features);
            $len2 = count($face2Features);

            if ($len1 !== $len2) {
                $targetLength = min($len1, $len2);
                $face1Features = array_slice($face1Features, 0, $targetLength);
                $face2Features = array_slice($face2Features, 0, $targetLength);
            }

            // Calculate cosine similarity
            $dotProduct = 0.0;
            $mag1 = 0.0;
            $mag2 = 0.0;
            
            for ($i = 0; $i < count($face1Features); $i++) {
                $dotProduct += $face1Features[$i] * $face2Features[$i];
                $mag1 += $face1Features[$i] * $face1Features[$i];
                $mag2 += $face2Features[$i] * $face2Features[$i];
            }
            
            $mag1 = sqrt($mag1);
            $mag2 = sqrt($mag2);
            
            if ($mag1 <= 0 || $mag2 <= 0) {
                return 0.0;
            }
            
            $cosineSimilarity = $dotProduct / ($mag1 * $mag2);
            
            // Convert from [-1, 1] range to [0, 1] range
            $similarity = ($cosineSimilarity + 1) / 2;
            
            // Face similarity is more lenient than visual similarity
            // Don't apply the cubic scaling for faces
            return $similarity;

        } catch (\Exception $e) {
            Log::error("Error calculating face feature similarity: " . $e->getMessage());
            return 0.0;
        }
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

    // Extract face features from image
    private function extractFaceFeatures($imagePath)
    {
        $pythonPath = 'python';
        $scriptPath = base_path('face_detection.py');

        $process = new Process([$pythonPath, $scriptPath, $imagePath]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(60); // Longer timeout for face detection

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Face detection Python script failed', [
                    'command' => $process->getCommandLine(),
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput(),
                    'exit_code' => $process->getExitCode()
                ]);
                // Return default values if face detection fails
                return [
                    'has_faces' => false,
                    'face_count' => 0,
                    'face_features' => [],
                    'face_rectangles' => []
                ];
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

            Log::info('Face detection completed', [
                'has_faces' => $faceData['has_faces'],
                'face_count' => $faceData['face_count']
            ]);

            return $faceData;

        } catch (\Exception $e) {
            Log::error('Exception in face detection', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            
            // Return default values if face detection fails
            return [
                'has_faces' => false,
                'face_count' => 0,
                'face_features' => [],
                'face_rectangles' => []
            ];
        }
    }




}
