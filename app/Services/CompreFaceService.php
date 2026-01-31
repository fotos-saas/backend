<?php

namespace App\Services;

use App\Models\Album;
use App\Models\FaceGroup;
use App\Models\Photo;
use App\Services\Contracts\FaceRecognitionServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CompreFace service implementation for face recognition.
 * Supports face detection, gender detection, and face pose analysis.
 */
class CompreFaceService implements FaceRecognitionServiceInterface
{
    protected string $baseUrl;

    protected string $detectionApiKey;

    protected string $recognitionApiKey;

    protected int $timeout;

    protected float $similarityThreshold;

    public function __construct()
    {
        $this->baseUrl = config('face-recognition.compreface.url');
        $this->detectionApiKey = config('face-recognition.compreface.api_key');
        $this->recognitionApiKey = config('face-recognition.compreface.recognition_api_key');
        $this->timeout = config('face-recognition.compreface.timeout', 60);
        $this->similarityThreshold = config('face-recognition.grouping.similarity_threshold', 0.7);
    }

    /**
     * Detect and group faces in album photos using CompreFace.
     */
    public function detectAndGroupFaces(Album $album, Collection $photos): void
    {
        Log::info('CompreFace face recognition started', [
            'album_id' => $album->id,
            'photo_count' => $photos->count(),
        ]);

        // Delete existing groups
        $album->faceGroups()->delete();

        $processedCount = 0;
        $subjectsData = collect();

        // Step 1: Detect faces and attributes in all photos
        foreach ($photos as $photo) {
            try {
                $detectionResult = $this->detectFaceWithAttributes($photo);

                if ($detectionResult) {
                    // Update photo with gender and face direction
                    $photo->update([
                        'gender' => $detectionResult['gender'],
                        'face_direction' => $detectionResult['face_direction'],
                    ]);

                    // Add face to recognition service
                    $subjectName = $this->addFaceToRecognition($photo, $album->id);

                    if ($subjectName) {
                        $subjectsData->push([
                            'photo' => $photo,
                            'subject' => $subjectName,
                            'confidence' => $detectionResult['confidence'],
                        ]);
                    }

                    Log::debug('Face detected', [
                        'photo_id' => $photo->id,
                        'gender' => $detectionResult['gender'],
                        'face_direction' => $detectionResult['face_direction'],
                        'subject' => $subjectName ?? 'none',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to detect face', [
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $processedCount++;

            // Update progress every 10 photos
            if ($processedCount % 10 === 0) {
                $album->update([
                    'face_processed_photos' => $processedCount,
                ]);
            }
        }

        Log::info('Face detection completed', [
            'album_id' => $album->id,
            'faces_detected' => $subjectsData->count(),
        ]);

        // Step 2: Create FaceGroups from subjects
        $this->createFaceGroupsFromSubjects($album, $subjectsData);

        // Final progress update
        $album->update([
            'face_processed_photos' => $processedCount,
        ]);

        Log::info('CompreFace face recognition completed', [
            'album_id' => $album->id,
            'photos_processed' => $processedCount,
            'groups_created' => $album->faceGroups()->count(),
        ]);
    }

    /**
     * Detect face with attributes (gender, pose) in a single photo.
     */
    protected function detectFaceWithAttributes(Photo $photo): ?array
    {
        $imagePath = $this->getPhotoPath($photo);

        if (! $imagePath || ! file_exists($imagePath)) {
            Log::warning('Photo file not found', [
                'photo_id' => $photo->id,
                'path' => $imagePath,
            ]);

            return null;
        }

        try {
            // Use CompreFace detection endpoint with age, gender, and pose attributes
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->detectionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/detection/detect", [
                    'age' => false,
                    'gender' => true,
                    'pose' => true,
                ]);

            if (! $response->successful()) {
                if ($response->status() === 404) {
                    return null; // No face detected
                }

                Log::error('CompreFace detection API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (empty($data['result'])) {
                return null; // No faces found
            }

            // Get first face
            $face = $data['result'][0];

            $confidence = $face['box']['probability'] ?? 0;

            // Extract gender
            $gender = $this->extractGender($face);

            // Extract face direction from pose
            $faceDirection = $this->extractFaceDirection($face);

            return [
                'confidence' => $confidence,
                'gender' => $gender,
                'face_direction' => $faceDirection,
                'box' => $face['box'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to detect face with CompreFace', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add face to CompreFace recognition service and return subject name.
     */
    protected function addFaceToRecognition(Photo $photo, int $albumId): ?string
    {
        $imagePath = $this->getPhotoPath($photo);

        if (! $imagePath || ! file_exists($imagePath)) {
            return null;
        }

        try {
            // Try to recognize existing face first
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->recognitionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/recognition/recognize", [
                    'limit' => 1,
                    'prediction_count' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if face matches existing subject
                if (! empty($data['result']) && ! empty($data['result'][0]['subjects'])) {
                    $subject = $data['result'][0]['subjects'][0];
                    if (($subject['similarity'] ?? 0) >= $this->similarityThreshold) {
                        return $subject['subject'];
                    }
                }
            }

            // No match found, create new subject
            $subjectName = "album_{$albumId}_photo_{$photo->id}";

            $addResponse = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->recognitionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/recognition/faces?subject={$subjectName}");

            if ($addResponse->successful()) {
                Log::debug('New subject created in CompreFace', [
                    'subject' => $subjectName,
                    'photo_id' => $photo->id,
                ]);

                return $subjectName;
            }

            Log::error('Failed to add face to CompreFace', [
                'photo_id' => $photo->id,
                'status' => $addResponse->status(),
                'body' => $addResponse->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to add face to recognition', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create FaceGroup records from detected subjects.
     */
    protected function createFaceGroupsFromSubjects(Album $album, Collection $subjectsData): void
    {
        if ($subjectsData->isEmpty()) {
            return;
        }

        // Group photos by subject
        $groupedBySubject = $subjectsData->groupBy('subject');

        $groupNumber = 1;
        foreach ($groupedBySubject as $subjectName => $photos) {
            // Pick photo with highest confidence as representative
            $representative = $photos->sortByDesc('confidence')->first();

            $faceGroup = FaceGroup::create([
                'album_id' => $album->id,
                'name' => config('face-recognition.grouping.auto_name_prefix', 'Csoport').' '.$groupNumber,
                'representative_photo_id' => $representative['photo']->id,
            ]);

            // Attach all photos to this group
            foreach ($photos as $photoData) {
                $photoData['photo']->faceGroups()->attach($faceGroup->id, [
                    'confidence' => $photoData['confidence'],
                ]);
            }

            Log::debug('Face group created', [
                'group_id' => $faceGroup->id,
                'subject' => $subjectName,
                'photos_count' => $photos->count(),
            ]);

            $groupNumber++;
        }

        Log::info('Face groups created from subjects', [
            'album_id' => $album->id,
            'groups_count' => $groupedBySubject->count(),
        ]);
    }

    /**
     * Extract gender from CompreFace response.
     */
    protected function extractGender(array $face): string
    {
        $gender = $face['gender'] ?? null;

        if (! $gender) {
            return 'unknown';
        }

        $value = strtolower($gender['value'] ?? '');
        $probability = $gender['probability'] ?? 0;

        // Only return gender if probability is high enough
        if ($probability < 0.6) {
            return 'unknown';
        }

        return match ($value) {
            'male' => 'male',
            'female' => 'female',
            default => 'unknown',
        };
    }

    /**
     * Extract face direction from pose data (yaw angle).
     */
    protected function extractFaceDirection(array $face): string
    {
        $pose = $face['pose'] ?? null;

        if (! $pose) {
            return 'unknown';
        }

        $yaw = $pose['yaw'] ?? 0;

        // Yaw angle interpretation:
        // -30 to 30 degrees: center (looking at camera)
        // < -30: left (face turned left)
        // > 30: right (face turned right)
        if ($yaw < -30) {
            return 'left';
        } elseif ($yaw > 30) {
            return 'right';
        } else {
            return 'center';
        }
    }

    /**
     * Get photo file path (preview or original).
     */
    protected function getPhotoPath(Photo $photo): ?string
    {
        $photoPath = $photo->path;

        if (! $photoPath) {
            return null;
        }

        // Build preview path from original path
        $pathInfo = pathinfo($photoPath);
        $previewPath = $pathInfo['dirname'].'/conversions/'.$pathInfo['filename'].'-preview.jpg';

        // If preview doesn't exist, fallback to original
        if (! file_exists($previewPath)) {
            return $photoPath;
        }

        return $previewPath;
    }

    /**
     * Calculate similarity between two embeddings (not used for CompreFace).
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        // CompreFace handles similarity internally
        // This method is kept for interface compatibility
        return 0.0;
    }

    /**
     * Detect face in a single photo (interface requirement).
     */
    public function detectFace(Photo $photo): ?array
    {
        $result = $this->detectFaceWithAttributes($photo);

        if (! $result) {
            return null;
        }

        return [
            'confidence' => $result['confidence'],
            'box' => $result['box'],
            'gender' => $result['gender'],
            'face_direction' => $result['face_direction'],
        ];
    }

    /**
     * Detect face with detailed attributes from image path.
     * Public method for use by jobs.
     */
    public function detectFaceFromPath(string $imagePath, array $options = []): ?array
    {
        if (! file_exists($imagePath)) {
            Log::warning('Image file not found', [
                'path' => $imagePath,
            ]);

            return null;
        }

        try {
            $plugins = $options['face_plugins'] ?? 'gender,age,pose';
            $threshold = $options['det_prob_threshold'] ?? 0.8;

            // Build URL with query parameters (do not encode commas in plugins)
            $url = "{$this->baseUrl}/api/v1/detection/detect?face_plugins={$plugins}&det_prob_threshold={$threshold}";

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->detectionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post($url);

            if (! $response->successful()) {
                if ($response->status() === 404 || $response->status() === 400) {
                    return ['result' => []]; // No face detected
                }

                Log::error('CompreFace detection API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to detect face from path', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add face to recognition service for grouping.
     * Public method for use by jobs.
     */
    public function addFace(string $imagePath, string $subjectName, int $albumId): bool
    {
        if (! file_exists($imagePath)) {
            return false;
        }

        try {
            // Try to recognize existing face first
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->recognitionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/recognition/recognize", [
                    'limit' => 1,
                    'prediction_count' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if face matches existing subject with high similarity
                if (! empty($data['result']) && ! empty($data['result'][0]['subjects'])) {
                    $subject = $data['result'][0]['subjects'][0];
                    if (($subject['similarity'] ?? 0) >= $this->similarityThreshold) {
                        // Face already exists with similar subject, merge them
                        $existingSubject = $subject['subject'];
                        Log::debug('Face matched existing subject', [
                            'new_subject' => $subjectName,
                            'existing_subject' => $existingSubject,
                            'similarity' => $subject['similarity'],
                        ]);

                        return true;
                    }
                }
            }

            // No match found or low similarity, add as new subject
            $addResponse = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->recognitionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/recognition/faces?subject={$subjectName}");

            if ($addResponse->successful()) {
                Log::debug('Face added to recognition service', [
                    'subject' => $subjectName,
                ]);

                return true;
            }

            Log::error('Failed to add face to CompreFace', [
                'subject' => $subjectName,
                'status' => $addResponse->status(),
                'body' => $addResponse->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to add face to recognition', [
                'subject' => $subjectName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cluster faces using pairwise verification and graph-based grouping.
     * Returns array of created face groups.
     */
    public function clusterFacesWithVerification(Album $album): array
    {
        Log::info('Starting face clustering with verification', [
            'album_id' => $album->id,
        ]);

        // Delete existing groups
        $album->faceGroups()->delete();

        // Get all photos with detected faces
        $photos = $album->photos()
            ->whereNotNull('gender')
            ->get();

        if ($photos->count() < 2) {
            Log::info('Not enough photos for clustering', [
                'album_id' => $album->id,
                'photos_count' => $photos->count(),
            ]);

            return [];
        }

        Log::info('Photos loaded for clustering', [
            'album_id' => $album->id,
            'photos_count' => $photos->count(),
        ]);

        // Step 1: Pairwise verification
        $similarities = [];
        $photoCount = $photos->count();

        for ($i = 0; $i < $photoCount; $i++) {
            for ($j = $i + 1; $j < $photoCount; $j++) {
                $similarity = $this->verifyFaces($photos[$i], $photos[$j]);

                if ($similarity >= $this->similarityThreshold) {
                    $similarities[] = [
                        'photo1_idx' => $i,
                        'photo2_idx' => $j,
                        'similarity' => $similarity,
                    ];

                    Log::debug('Face match found', [
                        'photo1_id' => $photos[$i]->id,
                        'photo2_id' => $photos[$j]->id,
                        'similarity' => $similarity,
                    ]);
                }
            }
        }

        Log::info('Pairwise verification completed', [
            'album_id' => $album->id,
            'matches_found' => count($similarities),
        ]);

        // Step 2: Union-Find clustering
        $clusters = $this->unionFind($photoCount, $similarities);

        Log::info('Clustering completed', [
            'album_id' => $album->id,
            'clusters_found' => count($clusters),
        ]);

        // Step 3: Create FaceGroup records
        $createdGroups = [];
        $groupNumber = 1;

        foreach ($clusters as $cluster) {
            $clusterPhotos = collect($cluster)->map(fn ($idx) => $photos[$idx]);

            // Calculate average confidence (use detection confidence from photo metadata)
            $avgConfidence = $clusterPhotos->avg(fn ($photo) => 0.85); // Default confidence

            // Pick first photo as representative
            $representative = $clusterPhotos->first();

            $faceGroup = FaceGroup::create([
                'album_id' => $album->id,
                'name' => config('face-recognition.grouping.auto_name_prefix', 'Csoport').' '.$groupNumber,
                'representative_photo_id' => $representative->id,
            ]);

            // Attach all photos to this group
            foreach ($clusterPhotos as $photo) {
                $photo->faceGroups()->attach($faceGroup->id, [
                    'confidence' => $avgConfidence,
                ]);
            }

            Log::debug('Face group created from clustering', [
                'group_id' => $faceGroup->id,
                'group_name' => $faceGroup->name,
                'photos_count' => $clusterPhotos->count(),
            ]);

            $createdGroups[] = [
                'id' => $faceGroup->id,
                'name' => $faceGroup->name,
                'photos_count' => $clusterPhotos->count(),
            ];

            $groupNumber++;
        }

        Log::info('Face clustering completed', [
            'album_id' => $album->id,
            'groups_created' => count($createdGroups),
        ]);

        return $createdGroups;
    }

    /**
     * Verify similarity between two faces using CompreFace verification API.
     * Returns similarity score (0-1).
     */
    protected function verifyFaces(Photo $photo1, Photo $photo2): float
    {
        $imagePath1 = $this->getPhotoPath($photo1);
        $imagePath2 = $this->getPhotoPath($photo2);

        if (! $imagePath1 || ! file_exists($imagePath1) || ! $imagePath2 || ! file_exists($imagePath2)) {
            return 0.0;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->recognitionApiKey,
                ])
                ->attach('source_image', file_get_contents($imagePath1), basename($imagePath1))
                ->attach('target_image', file_get_contents($imagePath2), basename($imagePath2))
                ->post("{$this->baseUrl}/api/v1/verification/verify");

            if (! $response->successful()) {
                Log::warning('CompreFace verification API error', [
                    'status' => $response->status(),
                    'photo1_id' => $photo1->id,
                    'photo2_id' => $photo2->id,
                ]);

                return 0.0;
            }

            $data = $response->json();

            // CompreFace verification response format: { result: [{ similarity: 0.85 }] }
            $similarity = $data['result'][0]['similarity'] ?? 0.0;

            return (float) $similarity;
        } catch (\Exception $e) {
            Log::error('Failed to verify faces', [
                'photo1_id' => $photo1->id,
                'photo2_id' => $photo2->id,
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Union-Find algorithm for clustering.
     * Returns array of clusters (each cluster is array of photo indices).
     */
    protected function unionFind(int $count, array $edges): array
    {
        // Initialize parent array
        $parent = range(0, $count - 1);

        // Find function with path compression
        $find = function (int $x) use (&$parent, &$find): int {
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        // Union function
        $union = function (int $x, int $y) use (&$parent, &$find): void {
            $rootX = $find($x);
            $rootY = $find($y);

            if ($rootX !== $rootY) {
                $parent[$rootX] = $rootY;
            }
        };

        // Process all edges (similar face pairs)
        foreach ($edges as $edge) {
            $union($edge['photo1_idx'], $edge['photo2_idx']);
        }

        // Group by root
        $clusters = [];
        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            $clusters[$root][] = $i;
        }

        return array_values($clusters);
    }
}
