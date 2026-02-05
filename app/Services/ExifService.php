<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * EXIF metaadat kezelő service.
 *
 * SECURITY: Symfony Process komponenst használ shell_exec helyett
 * a parancs injekció megelőzésére.
 */
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
     *
     * SECURITY: Symfony Process használata shell_exec helyett
     */
    public function isExifToolAvailable(): bool
    {
        $process = new Process(['which', 'exiftool']);
        $process->run();

        return $process->isSuccessful() && !empty(trim($process->getOutput()));
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

        // SECURITY: Symfony Process használata shell_exec helyett
        // Write EXIF metadata (multiple tags for maximum compatibility)
        $process = new Process([
            'exiftool',
            "-Title={$title}",           // EXIF Title
            "-XMP:Title={$title}",       // XMP Title
            "-IPTC:Headline={$title}",   // IPTC Headline
            "-Description={$title}",     // EXIF Description
            "-XMP:Description={$title}", // XMP Description
            "-Creator={$title}",         // EXIF Creator
            "-XMP:Creator={$title}",     // XMP Creator
            "-Artist={$title}",          // EXIF Artist
            '-overwrite_original',
            $destPath,
        ]);

        $process->run();
        $output = $process->getOutput() . $process->getErrorOutput();

        Log::info('EXIF metadata written', [
            'file' => basename($destPath),
            'title' => $title,
            'output' => trim($output),
        ]);

        return file_exists($destPath);
    }
}
