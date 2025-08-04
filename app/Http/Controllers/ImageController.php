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

                Log::info("Similarity with image ID {$img->id}: $similarity (threshold: 0.1)");

                $allScores[] = [
                    'image' => $img,
                    'similarity' => $similarity,
                ];

                // Add images to results with reasonable similarity threshold
                if ($similarity >= 0.5) {
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
        Log::info("Search completed: " . count($allScores) . " images processed, " . count($results) . " above threshold (0.5)");
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

        // Apply a more strict transformation to better distinguish between similar and dissimilar objects
        // This makes the similarity scores more discriminative
        $similarity = max(0, min(1, $similarity * 0.6));

        return $similarity;
    }
}
