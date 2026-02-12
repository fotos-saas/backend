<?php

namespace App\Services\FaceRecognition;

use App\Models\Photo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Face Detection Service
 *
 * CompreFace arcfelismerés és attribútum kinyerés:
 * - Arc detektálás (detection API)
 * - Nem meghatározás (gender)
 * - Arc irány meghatározás (pose/yaw)
 * - Fotó elérési út kezelés
 */
class FaceDetectionService
{
    protected string $baseUrl;

    protected string $detectionApiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('face-recognition.compreface.url');
        $this->detectionApiKey = config('face-recognition.compreface.api_key');
        $this->timeout = config('face-recognition.compreface.timeout', 60);
    }

    /**
     * Detect face with attributes (gender, pose) in a single photo.
     */
    public function detectFaceWithAttributes(Photo $photo): ?array
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
                    return null;
                }

                Log::error('CompreFace detection API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (empty($data['result'])) {
                return null;
            }

            $face = $data['result'][0];
            $confidence = $face['box']['probability'] ?? 0;
            $gender = $this->extractGender($face);
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

            $url = "{$this->baseUrl}/api/v1/detection/detect?face_plugins={$plugins}&det_prob_threshold={$threshold}";

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->detectionApiKey,
                ])
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post($url);

            if (! $response->successful()) {
                if ($response->status() === 404 || $response->status() === 400) {
                    return ['result' => []];
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
     * Extract gender from CompreFace response.
     */
    public function extractGender(array $face): string
    {
        $gender = $face['gender'] ?? null;

        if (! $gender) {
            return 'unknown';
        }

        $value = strtolower($gender['value'] ?? '');
        $probability = $gender['probability'] ?? 0;

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
    public function extractFaceDirection(array $face): string
    {
        $pose = $face['pose'] ?? null;

        if (! $pose) {
            return 'unknown';
        }

        $yaw = $pose['yaw'] ?? 0;

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
    public function getPhotoPath(Photo $photo): ?string
    {
        $photoPath = $photo->path;

        if (! $photoPath) {
            return null;
        }

        $pathInfo = pathinfo($photoPath);
        $previewPath = $pathInfo['dirname'] . '/conversions/' . $pathInfo['filename'] . '-preview.jpg';

        if (! file_exists($previewPath)) {
            return $photoPath;
        }

        return $previewPath;
    }
}
