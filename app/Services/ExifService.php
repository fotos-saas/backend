<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ExifService
{
    /**
     * Constructor - validates exiftool availability
     */
    public function __construct()
    {
        if (!$this->isExifToolAvailable()) {
            Log::warning('exiftool is not installed - EXIF features will be limited');
            // NE dobjon exception-t, csak warning, hogy ne törjön el a rendszer
        }
    }

    /**
     * Check if exiftool is available
     */
    public function isExifToolAvailable(): bool
    {
        return !empty(shell_exec('which exiftool 2>/dev/null'));
    }

    /**
     * Set EXIF Title metadata
     *
     * @param string $sourcePath Original file path
     * @param string $destPath Destination file path
     * @param string $title Title to write to EXIF
     * @return bool Success
     */
    public function setTitleMetadata(string $sourcePath, string $destPath, string $title): bool
    {
        if (!$this->isExifToolAvailable()) {
            Log::warning('exiftool not available, skipping EXIF write');
            return false;
        }

        if (!file_exists($sourcePath)) {
            Log::error('Source file does not exist', ['path' => $sourcePath]);
            return false;
        }

        // Copy file
        if (!copy($sourcePath, $destPath)) {
            Log::error('Failed to copy file', [
                'source' => $sourcePath,
                'dest' => $destPath,
            ]);
            return false;
        }

        // Write EXIF metadata (multiple tags for maximum compatibility)
        $escapedPath = escapeshellarg($destPath);
        $escapedTitle = escapeshellarg($title);

        // Write to multiple EXIF/XMP/IPTC tags for compatibility with different viewers
        $command = "exiftool " .
            "-Title={$escapedTitle} " .                  // EXIF Title
            "-XMP:Title={$escapedTitle} " .              // XMP Title
            "-IPTC:Headline={$escapedTitle} " .          // IPTC Headline
            "-Description={$escapedTitle} " .            // EXIF Description
            "-XMP:Description={$escapedTitle} " .        // XMP Description
            "-Creator={$escapedTitle} " .                // EXIF Creator
            "-XMP:Creator={$escapedTitle} " .            // XMP Creator
            "-Artist={$escapedTitle} " .                 // EXIF Artist
            "-overwrite_original {$escapedPath} 2>&1";

        $output = shell_exec($command);

        Log::info('EXIF metadata written', [
            'file' => basename($destPath),
            'title' => $title,
            'output' => trim($output),
        ]);

        return file_exists($destPath);
    }
}
