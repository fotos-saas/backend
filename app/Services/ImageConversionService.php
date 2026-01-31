<?php

namespace App\Services;

use App\Models\ConversionJob;
use App\Models\ConversionMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageConversionService
{
    /**
     * Store image with Spatie Media Library
     *
     * @param bool $skipConversions Skip thumbnail/preview generation for fast upload
     */
    public function storeImage(
        ConversionJob $job,
        UploadedFile $file,
        ?string $folderPath = null,
        bool $skipConversions = false
    ): ConversionMedia {
        // Extract original filename
        $originalName = $file->getClientOriginalName();

        // Check if hidden file
        if ($this->isHiddenFile($originalName)) {
            throw new \InvalidArgumentException('Hidden files are not allowed');
        }

        // Sanitize folder path to prevent path traversal attacks
        $sanitizedFolderPath = $this->sanitizeFolderPath($folderPath);

        // Create ConversionMedia record
        $conversionMedia = ConversionMedia::create([
            'conversion_job_id' => $job->id,
            'folder_path' => $sanitizedFolderPath,
            'conversion_status' => 'pending',
            'skip_initial_conversions' => $skipConversions,
        ]);

        // Generate ULID-based unique filename
        $extension = $file->getClientOriginalExtension();
        $uniqueFilename = strtoupper(Str::ulid()->toString()).'.'.$extension;

        // CRITICAL: Set global flag to skip conversions if requested
        if ($skipConversions) {
            ConversionMedia::$skipConversions = true;
        }

        // Add media with custom properties
        $conversionMedia->addMedia($file)
            ->usingFileName($uniqueFilename)
            ->withCustomProperties([
                'original_name' => $originalName,
                'folder_path' => $sanitizedFolderPath,
            ])
            ->toMediaCollection('image_conversion', 'public');

        // Reset flag
        if ($skipConversions) {
            ConversionMedia::$skipConversions = false;
        }

        // Mark upload as completed
        $conversionMedia->update([
            'upload_completed_at' => now(),
        ]);

        return $conversionMedia;
    }

    /**
     * Sanitize folder path to prevent path traversal attacks
     * Allows Hungarian accented characters (á, é, í, ó, ö, ő, ú, ü, ű)
     */
    protected function sanitizeFolderPath(?string $folderPath): ?string
    {
        if (! $folderPath) {
            return null;
        }

        // Remove path traversal attempts (../, ..\, etc.)
        $folderPath = str_replace(['../', '..\\', '\\'], '', $folderPath);

        // Remove leading/trailing slashes
        $folderPath = trim($folderPath, '/\\');

        // Only allow alphanumeric, spaces, hyphens, underscores, forward slashes, parentheses, and Hungarian accented chars
        $folderPath = preg_replace('/[^a-zA-Z0-9\s\-_\/()áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/u', '', $folderPath);

        // Limit length to 255 characters
        $folderPath = Str::limit($folderPath, 255, '');

        return empty($folderPath) ? null : $folderPath;
    }

    /**
     * Check if file is hidden (starts with dot)
     */
    public function isHiddenFile(string $filename): bool
    {
        return str_starts_with(basename($filename), '.');
    }

    /**
     * Extract folder path from file path
     */
    public function extractFolderPath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $pathParts = explode('/', $relativePath);

        // Remove filename (last element)
        array_pop($pathParts);

        // If no folders, return null
        if (empty($pathParts)) {
            return null;
        }

        return implode('/', $pathParts);
    }

    /**
     * Generate job name with timestamp
     */
    public function generateJobName(): string
    {
        return 'conversion_'.now()->format('Y_m_d_His');
    }

    /**
     * Validate image file
     */
    public function validateImageFile(UploadedFile $file): bool
    {
        // Supported formats: HEIC, WEBP, JPEG, PNG, JPG, BMP
        $supportedMimes = [
            'image/heic',
            'image/heif',
            'image/webp',
            'image/jpeg',
            'image/png',
            'image/jpg',
            'image/bmp',
            'image/x-ms-bmp',
        ];

        $mimeType = $file->getMimeType();

        return in_array($mimeType, $supportedMimes);
    }

    /**
     * Get supported file extensions
     */
    public function getSupportedExtensions(): array
    {
        return ['heic', 'heif', 'webp', 'jpeg', 'jpg', 'png', 'bmp'];
    }

    /**
     * Create or update conversion job
     */
    public function createJob(?string $jobName = null): ConversionJob
    {
        return ConversionJob::create([
            'job_name' => $jobName ?? $this->generateJobName(),
            'status' => 'pending',
            'total_files' => 0,
            'processed_files' => 0,
        ]);
    }

    /**
     * Update job progress with phase tracking
     */
    public function updateJobProgress(ConversionJob $job): void
    {
        // Single aggregated query with phase tracking
        $stats = $job->media()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN conversion_status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN conversion_status = ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN upload_completed_at IS NOT NULL THEN 1 ELSE 0 END) as uploaded,
                SUM(CASE WHEN conversion_started_at IS NOT NULL THEN 1 ELSE 0 END) as converting
            ', ['completed', 'failed'])
            ->first();

        $totalMedia = $stats->total ?? 0;
        $completedMedia = $stats->completed ?? 0;
        $failedMedia = $stats->failed ?? 0;
        $uploadedMedia = $stats->uploaded ?? 0;

        $job->update([
            'total_files' => $totalMedia,
            'processed_files' => $completedMedia + $failedMedia,
        ]);

        // Update job status based on progress
        if ($completedMedia + $failedMedia === $totalMedia && $totalMedia > 0) {
            $job->update(['status' => 'completed']);
        } elseif ($uploadedMedia === $totalMedia && $totalMedia > 0) {
            $job->update(['status' => 'uploaded']); // All uploaded, ready for conversion
        } elseif ($uploadedMedia > 0) {
            $job->update(['status' => 'uploading']);
        }
    }

    /**
     * Wait for thumbnail generation to complete
     *
     * Spatie Media Library generates thumbnails asynchronously even with nonQueued().
     * This method blocks until the thumbnail file exists on disk.
     *
     * @param ConversionMedia $media
     * @param string $conversionName (thumb, preview, etc.)
     * @param int $maxAttempts Maximum polling attempts (default: 20)
     * @param int $delayMs Delay between attempts in milliseconds (default: 100ms)
     * @return bool True if thumbnail exists, false if timeout
     */
    public function waitForThumbnailGeneration(
        ConversionMedia $media,
        string $conversionName = 'thumb',
        int $maxAttempts = 20,
        int $delayMs = 100
    ): bool {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            // Refresh media relationship from database
            $media->load('media');
            $spatieMedia = $media->getFirstMedia('image_conversion');

            if (!$spatieMedia) {
                // Original media not yet saved
                $attempt++;
                usleep($delayMs * 1000); // Convert ms to microseconds
                continue;
            }

            // Check if thumbnail file exists on disk
            $thumbnailPath = $spatieMedia->getPath($conversionName);

            if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
                return true; // Thumbnail successfully generated
            }

            $attempt++;
            usleep($delayMs * 1000); // Convert ms to microseconds
        }

        // Timeout: thumbnail not generated within max attempts
        \Log::warning('Thumbnail generation timeout', [
            'media_id' => $media->id,
            'conversion' => $conversionName,
            'max_attempts' => $maxAttempts,
            'total_wait_ms' => $maxAttempts * $delayMs,
        ]);

        return false;
    }
}
