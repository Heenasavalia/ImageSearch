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
            'image' => 'required|image|max:10240', // 10MB max
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

            // Extract face features
            $faceData = $this->extractFaceFeatures($fullPath);

            if (!$faceData['has_faces']) {
                // Delete the uploaded file if no faces detected
                Storage::disk('public')->delete($path);
                return back()->with('error', 'No human faces detected in the uploaded image. Please upload an image with clear human faces.');
            }

            $image = new Image();
            $image->path = $path;
            $image->has_faces = $faceData['has_faces'];
            $image->face_count = $faceData['face_count'];
            $image->face_features = $faceData['face_features'];
            $image->face_rectangles = $faceData['face_rectangles'];
            $image->save();

            // $message = 'Image uploaded successfully! Found ' . $faceData['face_count'] . ' face(s).';
            $message = 'Image uploaded successfully!';

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

    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        $file = $request->file('image');
        $path = $file->store('searches', 'public');
        $fullPath = storage_path('app/public/' . $path);

        // Wait for file to be fully written
        $waits = 0;
        while ((!file_exists($fullPath) || filesize($fullPath) == 0) && $waits < 10) {
            usleep(200000); // wait 0.2s
            $waits++;
        }

        // Check if file is a valid image
        if (!@getimagesize($fullPath)) {
            return back()->with('error', 'Uploaded file is not a valid image or could not be processed.');
        }

        // Extract face features from the uploaded image
        $searchFaceData = $this->extractFaceFeatures($fullPath);

        if (!$searchFaceData['has_faces']) {
            return back()->with('error', 'No faces detected in the uploaded image. Please upload an image with a clear human face.');
        }

        Log::info("Face search: Found " . $searchFaceData['face_count'] . " face(s) in search image");

        // Get all images with faces from database
        $images = Image::where('has_faces', true)
                      ->whereNotNull('face_features')
                      ->get();

        $results = [];
        $allScores = [];
        
        foreach ($images as $img) {
            try {
                // Calculate face similarity
                $faceSimilarity = $this->calculateFaceSimilarity($searchFaceData, $img);
                
                // Much stricter threshold for face matching - only very similar faces
                if ($faceSimilarity > 0.90) { // Increased from 0.75 to 0.90
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
                    'has_face_match' => $faceSimilarity > 0.90
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

        return view('images.results', [
            'results' => $results,
            'allScores' => $allScores,
            'searchFaceData' => $searchFaceData,
            'totalImagesInDatabase' => $images->count()
        ]);
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
            
            // Apply extremely strict face matching thresholds
            // Only faces that are almost identical should get high scores
            if ($similarity < 0.95) {
                $similarity = $similarity * 0.1; // Heavily penalize anything below 95%
            } elseif ($similarity < 0.98) {
                $similarity = $similarity * 0.3; // Still penalize moderately low similarities
            }
            
            return $similarity;

        } catch (\Exception $e) {
            Log::error("Error calculating face feature similarity: " . $e->getMessage());
            return 0.0;
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
