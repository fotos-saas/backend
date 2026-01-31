<?php

namespace App\Services;

use App\Models\ConversionJob;
use ZipArchive;

class ZipGeneratorService
{
    /**
     * Hungarian character transliteration map
     */
    private const ACCENT_MAP = [
        'á' => 'a', 'Á' => 'A',
        'é' => 'e', 'É' => 'E',
        'í' => 'i', 'Í' => 'I',
        'ó' => 'o', 'Ó' => 'O',
        'ö' => 'o', 'Ö' => 'O',
        'ő' => 'o', 'Ő' => 'O',
        'ú' => 'u', 'Ú' => 'U',
        'ü' => 'u', 'Ü' => 'U',
        'ű' => 'u', 'Ű' => 'U',
    ];

    /**
     * Generate ZIP file from conversion job with folder structure
     */
    public function generateZip(ConversionJob $job): string
    {
        $zipFileName = storage_path('app/temp/'.$job->job_name.'_'.time().'.zip');

        // Create temp directory if it doesn't exist
        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP file');
        }

        // Eager load media with Spatie Media Library relationship to prevent N+1
        $job->load('media.media');

        // Root folder name = sanitized job_name
        $rootFolder = $this->sanitizeFolderName($job->job_name);

        // Add each media file to ZIP with folder structure
        foreach ($job->media as $media) {
            $mediaFile = $media->getFirstMedia('image_conversion');

            if (! $mediaFile) {
                continue;
            }

            // A ConvertImageBatchJob az eredeti fájlt cseréli ki a konvertáltra,
            // ezért a fő fájlt kell használni
            $convertedPath = $mediaFile->getPath();

            // Get original filename
            $originalName = $mediaFile->getCustomProperty('original_name') ?? $mediaFile->file_name;

            // Replace extension with .jpg since all files are converted to JPEG
            $originalName = preg_replace('/\.(heic|heif|webp|avif|jxl|dng|cr2|nef|arw|orf|rw2|png|bmp)$/i', '.jpg', $originalName);

            // Sanitize filename too (remove accents from filename)
            $originalName = $this->sanitizeFilename($originalName);

            // Build ZIP internal path with folder structure (root = job_name)
            $zipPath = $this->buildZipPath($rootFolder, $media->folder_path, $originalName);

            // Add file to ZIP
            $zip->addFile($convertedPath, $zipPath);
        }

        $zip->close();

        return $zipFileName;
    }

    /**
     * Sanitize folder name: remove accents, parentheses, special chars
     */
    protected function sanitizeFolderName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        // Remove accents
        $name = strtr($name, self::ACCENT_MAP);

        // Remove parentheses and their content or just the parentheses
        // Keep the content but remove parentheses: "(11)" -> "11"
        $name = str_replace(['(', ')'], '', $name);

        // Replace special characters with underscore, keep alphanumeric, space, dash, underscore, slash
        $name = preg_replace('/[^a-zA-Z0-9\s\-_\/]/', '', $name);

        // Replace multiple spaces/underscores with single
        $name = preg_replace('/[\s_]+/', '_', $name);

        // Trim underscores from start/end
        return trim($name, '_');
    }

    /**
     * Sanitize filename: remove accents but keep extension
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Get extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Remove accents from basename
        $basename = strtr($basename, self::ACCENT_MAP);

        // Remove special characters, keep alphanumeric, space, dash, underscore
        $basename = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $basename);

        // Replace multiple spaces/underscores with single
        $basename = preg_replace('/[\s_]+/', '_', $basename);

        // Trim
        $basename = trim($basename, '_');

        return $basename.'.'.$extension;
    }

    /**
     * Build ZIP internal path with folder structure
     * Root folder is always the job_name
     */
    protected function buildZipPath(string $rootFolder, ?string $folderPath, string $filename): string
    {
        // Sanitize the folder path (remove accents, parentheses)
        $sanitizedFolderPath = $folderPath ? $this->sanitizeFolderName($folderPath) : null;

        if ($sanitizedFolderPath) {
            return $rootFolder.'/'.$sanitizedFolderPath.'/'.$filename;
        }

        return $rootFolder.'/'.$filename;
    }

    /**
     * Clean up ZIP file after download
     */
    public function cleanup(string $zipFilePath): void
    {
        if (file_exists($zipFilePath)) {
            unlink($zipFilePath);
        }
    }

    /**
     * Get estimated ZIP size
     */
    public function getEstimatedSize(ConversionJob $job): int
    {
        // Eager load media with Spatie Media Library relationship to prevent N+1
        $job->load('media.media');

        $totalSize = 0;

        foreach ($job->media as $media) {
            $mediaFile = $media->getFirstMedia('image_conversion');

            if ($mediaFile) {
                // A ConvertImageBatchJob az eredeti fájlt cseréli ki a konvertáltra
                $convertedPath = $mediaFile->getPath();

                if (file_exists($convertedPath)) {
                    $totalSize += filesize($convertedPath);
                }
            }
        }

        return $totalSize;
    }
}
