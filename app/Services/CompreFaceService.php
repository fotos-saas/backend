<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Services\Contracts\FaceRecognitionServiceInterface;
use App\Services\FaceRecognition\FaceClusteringService;
use App\Services\FaceRecognition\FaceDetectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * CompreFace service - Facade delegáló.
 *
 * Backward compatibility wrapper: minden hívó változatlanul hivatkozhat erre a service-re.
 * A tényleges logikát a FaceDetectionService és FaceClusteringService végzi.
 */
class CompreFaceService implements FaceRecognitionServiceInterface
{
    public function __construct(
        private readonly FaceDetectionService $detectionService,
        private readonly FaceClusteringService $clusteringService,
    ) {}

    /**
     * Detect and group faces in album photos using CompreFace.
     */
    public function detectAndGroupFaces(Album $album, Collection $photos): void
    {
        Log::info('CompreFace face recognition started', [
            'album_id' => $album->id,
            'photo_count' => $photos->count(),
        ]);

        $album->faceGroups()->delete();

        $processedCount = 0;
        $subjectsData = collect();

        foreach ($photos as $photo) {
            try {
                $detectionResult = $this->detectionService->detectFaceWithAttributes($photo);

                if ($detectionResult) {
                    $photo->update([
                        'gender' => $detectionResult['gender'],
                        'face_direction' => $detectionResult['face_direction'],
                    ]);

                    $subjectName = $this->clusteringService->addFaceToRecognition($photo, $album->id);

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

        $this->clusteringService->createFaceGroupsFromSubjects($album, $subjectsData);

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
     * Detect face in a single photo (interface requirement).
     */
    public function detectFace(Photo $photo): ?array
    {
        return $this->detectionService->detectFace($photo);
    }

    /**
     * Detect face with detailed attributes from image path.
     */
    public function detectFaceFromPath(string $imagePath, array $options = []): ?array
    {
        return $this->detectionService->detectFaceFromPath($imagePath, $options);
    }

    /**
     * Add face to recognition service for grouping.
     */
    public function addFace(string $imagePath, string $subjectName, int $albumId): bool
    {
        return $this->clusteringService->addFace($imagePath, $subjectName, $albumId);
    }

    /**
     * Cluster faces using pairwise verification and graph-based grouping.
     */
    public function clusterFacesWithVerification(Album $album): array
    {
        return $this->clusteringService->clusterFacesWithVerification($album);
    }

    /**
     * Calculate similarity between two embeddings (not used for CompreFace).
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        return 0.0;
    }
}
