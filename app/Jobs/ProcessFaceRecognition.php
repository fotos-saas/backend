<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\CompreFaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process face recognition for a single photo.
 * Detects faces, extracts attributes (gender, age, pose), and prepares for grouping.
 */
class ProcessFaceRecognition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Photo $photo
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CompreFaceService $faceService): void
    {
        Log::info('Processing face recognition for photo', [
            'photo_id' => $this->photo->id,
            'album_id' => $this->photo->album_id,
        ]);

        try {
            // Check if album is being reprocessed (should overwrite existing data)
            $album = $this->photo->album;
            $shouldOverwrite = $album && $album->face_grouping_status === 'pending';

            // Detect face with attributes
            $result = $this->detectFaceWithAttributes($faceService);

            if ($result) {
                // Build update data - only update if empty or reprocessing
                $updateData = ['face_detected' => true];

                if ($shouldOverwrite || ! $this->photo->gender) {
                    $updateData['gender'] = $result['gender'] ?? null;
                }

                if ($shouldOverwrite || ! $this->photo->age) {
                    $updateData['age'] = $result['age'] ?? null;
                }

                if ($shouldOverwrite || ! $this->photo->face_direction) {
                    $updateData['face_direction'] = $result['face_direction'] ?? null;
                }

                // Add face to recognition service for grouping
                if ($result['has_face']) {
                    $subjectName = $this->addFaceToRecognition($faceService);

                    // Update subject if recognition succeeded and should overwrite or is empty
                    if ($subjectName && ($shouldOverwrite || ! $this->photo->face_subject)) {
                        $updateData['face_subject'] = $subjectName;
                    }
                }

                // Single update with all data
                $this->photo->update($updateData);

                Log::info('Face recognition completed', [
                    'photo_id' => $this->photo->id,
                    'has_face' => $result['has_face'],
                    'gender' => $updateData['gender'] ?? 'unchanged',
                    'age' => $updateData['age'] ?? 'unchanged',
                    'overwrite_mode' => $shouldOverwrite,
                ]);
            } else {
                // No face detected
                $this->photo->update([
                    'face_detected' => false,
                ]);

                Log::info('No face detected in photo', [
                    'photo_id' => $this->photo->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Face recognition failed', [
                'photo_id' => $this->photo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $this->photo->update([
                'face_detected' => false,
            ]);

            throw $e;
        }
    }

    /**
     * Detect face with attributes in the photo.
     */
    protected function detectFaceWithAttributes(CompreFaceService $faceService): ?array
    {
        $imagePath = $this->getPhotoPath();

        if (! $imagePath || ! file_exists($imagePath)) {
            Log::warning('Photo file not found', [
                'photo_id' => $this->photo->id,
                'path' => $imagePath,
            ]);

            return null;
        }

        try {
            $response = $faceService->detectFaceFromPath($imagePath, [
                'det_prob_threshold' => 0.8,
                'face_plugins' => 'gender,age,pose',
            ]);

            if (empty($response) || empty($response['result'])) {
                return [
                    'has_face' => false,
                    'gender' => null,
                    'age' => null,
                    'face_direction' => null,
                ];
            }

            $face = $response['result'][0];

            // Extract gender from CompreFace response
            $genderData = $face['gender'] ?? null;
            $gender = 'unknown';
            if ($genderData && isset($genderData['value'])) {
                $gender = strtolower($genderData['value']);
            }

            // Extract age
            $ageData = $face['age'] ?? null;
            $age = null;
            if ($ageData && isset($ageData['low'], $ageData['high'])) {
                $age = (int) (($ageData['low'] + $ageData['high']) / 2);
            }

            return [
                'has_face' => true,
                'gender' => $gender,
                'age' => $age,
                'face_direction' => $this->determineFaceDirection($face['pose'] ?? []),
                'confidence' => $face['box']['probability'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Face detection API call failed', [
                'photo_id' => $this->photo->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add detected face to recognition service for grouping.
     * Returns the subject name if successful, null otherwise.
     */
    protected function addFaceToRecognition(CompreFaceService $faceService): ?string
    {
        $imagePath = $this->getPhotoPath();

        if (! $imagePath || ! file_exists($imagePath)) {
            return null;
        }

        try {
            // Use photo ID as subject for now - grouping will merge similar faces
            $subjectName = "photo_{$this->photo->id}";

            $success = $faceService->addFace(
                $imagePath,
                $subjectName,
                $this->photo->album_id
            );

            if ($success) {
                Log::debug('Face added to recognition service', [
                    'photo_id' => $this->photo->id,
                    'subject' => $subjectName,
                ]);

                return $subjectName;
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to add face to recognition service', [
                'photo_id' => $this->photo->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get photo file path.
     * Prioritizes preview media conversion (~1200px) to reduce memory usage.
     */
    protected function getPhotoPath(): ?string
    {
        // Try preview media conversion first (~1200px, much smaller than original)
        $mediaItem = $this->photo->getFirstMedia('photo');
        if ($mediaItem) {
            try {
                $previewPath = $mediaItem->getPath('preview');
                if (file_exists($previewPath)) {
                    Log::debug('Using preview media for face recognition', [
                        'photo_id' => $this->photo->id,
                        'path' => $previewPath,
                    ]);

                    return $previewPath;
                }
            } catch (\Exception $e) {
                // Preview not available, fall back to original
                Log::debug('Preview media not available, falling back to original', [
                    'photo_id' => $this->photo->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback to original
            return $mediaItem->getPath();
        }

        // Check if path is already absolute
        if ($this->photo->path && file_exists($this->photo->path)) {
            return $this->photo->path;
        }

        // Try relative path with storage prefix
        if ($this->photo->path) {
            $storagePath = storage_path('app/public/'.$this->photo->path);
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return null;
    }

    /**
     * Determine face direction from pose data.
     */
    protected function determineFaceDirection(array $pose): string
    {
        $yaw = $pose['yaw'] ?? 0;
        $pitch = $pose['pitch'] ?? 0;

        if (abs($yaw) < 15 && abs($pitch) < 15) {
            return 'front';
        }

        if (abs($yaw) > 45) {
            return $yaw > 0 ? 'right' : 'left';
        }

        if (abs($pitch) > 30) {
            return $pitch > 0 ? 'up' : 'down';
        }

        return 'front';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Face recognition job failed permanently', [
            'photo_id' => $this->photo->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark photo as processed but failed
        $this->photo->update([
            'face_detected' => false,
        ]);
    }
}
