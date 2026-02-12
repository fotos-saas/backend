<?php

namespace App\Services\FaceRecognition;

use App\Models\Album;
use App\Models\FaceGroup;
use App\Models\Photo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Face Clustering Service
 *
 * Arcok csoportosítása és felismerése:
 * - Arc hozzáadás recognition service-hez
 * - Pairwise verification (arc-összehasonlítás)
 * - Union-Find clustering algoritmus
 * - FaceGroup-ok létrehozása
 */
class FaceClusteringService
{
    protected string $baseUrl;

    protected string $recognitionApiKey;

    protected int $timeout;

    protected float $similarityThreshold;

    public function __construct()
    {
        $this->baseUrl = config('face-recognition.compreface.url');
        $this->recognitionApiKey = config('face-recognition.compreface.recognition_api_key');
        $this->timeout = config('face-recognition.compreface.timeout', 60);
        $this->similarityThreshold = config('face-recognition.grouping.similarity_threshold', 0.7);
    }

    /**
     * Add face to CompreFace recognition service and return subject name.
     */
    public function addFaceToRecognition(Photo $photo, int $albumId): ?string
    {
        $detectionService = app(FaceDetectionService::class);
        $imagePath = $detectionService->getPhotoPath($photo);

        if (! $imagePath || ! file_exists($imagePath)) {
            return null;
        }

        return $this->addFaceFromPath($imagePath, "album_{$albumId}_photo_{$photo->id}");
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

        $result = $this->addFaceFromPath($imagePath, $subjectName);

        return $result !== null;
    }

    /**
     * Internal: add face from file path, return subject name or null.
     */
    private function addFaceFromPath(string $imagePath, string $subjectName): ?string
    {
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

                if (! empty($data['result']) && ! empty($data['result'][0]['subjects'])) {
                    $subject = $data['result'][0]['subjects'][0];
                    if (($subject['similarity'] ?? 0) >= $this->similarityThreshold) {
                        Log::debug('Face matched existing subject', [
                            'new_subject' => $subjectName,
                            'existing_subject' => $subject['subject'],
                            'similarity' => $subject['similarity'],
                        ]);

                        return $subject['subject'];
                    }
                }
            }

            // No match found, add as new subject
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

                return $subjectName;
            }

            Log::error('Failed to add face to CompreFace', [
                'subject' => $subjectName,
                'status' => $addResponse->status(),
                'body' => $addResponse->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to add face to recognition', [
                'subject' => $subjectName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create FaceGroup records from detected subjects.
     */
    public function createFaceGroupsFromSubjects(Album $album, Collection $subjectsData): void
    {
        if ($subjectsData->isEmpty()) {
            return;
        }

        $groupedBySubject = $subjectsData->groupBy('subject');

        $groupNumber = 1;
        foreach ($groupedBySubject as $subjectName => $photos) {
            $representative = $photos->sortByDesc('confidence')->first();

            $faceGroup = FaceGroup::create([
                'album_id' => $album->id,
                'name' => config('face-recognition.grouping.auto_name_prefix', 'Csoport') . ' ' . $groupNumber,
                'representative_photo_id' => $representative['photo']->id,
            ]);

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
     * Cluster faces using pairwise verification and graph-based grouping.
     */
    public function clusterFacesWithVerification(Album $album): array
    {
        Log::info('Starting face clustering with verification', [
            'album_id' => $album->id,
        ]);

        $album->faceGroups()->delete();

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

            $avgConfidence = $clusterPhotos->avg(fn ($photo) => 0.85);
            $representative = $clusterPhotos->first();

            $faceGroup = FaceGroup::create([
                'album_id' => $album->id,
                'name' => config('face-recognition.grouping.auto_name_prefix', 'Csoport') . ' ' . $groupNumber,
                'representative_photo_id' => $representative->id,
            ]);

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
     */
    protected function verifyFaces(Photo $photo1, Photo $photo2): float
    {
        $detectionService = app(FaceDetectionService::class);
        $imagePath1 = $detectionService->getPhotoPath($photo1);
        $imagePath2 = $detectionService->getPhotoPath($photo2);

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
        $parent = range(0, $count - 1);

        $find = function (int $x) use (&$parent, &$find): int {
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        $union = function (int $x, int $y) use (&$parent, &$find): void {
            $rootX = $find($x);
            $rootY = $find($y);

            if ($rootX !== $rootY) {
                $parent[$rootX] = $rootY;
            }
        };

        foreach ($edges as $edge) {
            $union($edge['photo1_idx'], $edge['photo2_idx']);
        }

        $clusters = [];
        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            $clusters[$root][] = $i;
        }

        return array_values($clusters);
    }
}
