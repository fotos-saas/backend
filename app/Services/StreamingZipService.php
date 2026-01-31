<?php

namespace App\Services;

use App\Models\ConversionJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ZipStream\ZipStream;
use ZipStream\Exception\FileNotFoundException;

class StreamingZipService
{
    /**
     * Chunk méret streaming-hez (1MB)
     */
    const CHUNK_SIZE = 1048576; // 1MB

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
     * Maximum memory használat (MB)
     */
    const MAX_MEMORY_MB = 128;

    /**
     * Progress cache TTL (perc)
     */
    const PROGRESS_CACHE_TTL = 60;

    /**
     * Generate streaming ZIP response
     *
     * @param ConversionJob $job
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamZip(ConversionJob $job): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Eager load media kapcsolatok az N+1 query elkerülésére
        $job->load('media.media');

        // ZIP fájlnév generálása
        $zipFileName = $this->generateZipFileName($job);

        // Cache key a progress tracking-hez
        $progressKey = $this->getProgressCacheKey($job);

        // Becsült méret kalkulálása
        $estimatedSize = $this->calculateEstimatedSize($job);

        // Teljes fájlok száma
        $totalFiles = $job->media->count();
        $processedFiles = 0;

        // Reset progress
        Cache::put($progressKey, [
            'status' => 'preparing',
            'processed' => 0,
            'total' => $totalFiles,
            'percentage' => 0,
            'current_file' => null,
            'bytes_written' => 0,
            'estimated_size' => $estimatedSize
        ], now()->addMinutes(self::PROGRESS_CACHE_TTL));

        // Root folder name = sanitized job_name
        $rootFolder = $this->sanitizePathName($job->job_name);

        return response()->streamDownload(
            function () use ($job, $progressKey, $totalFiles, &$processedFiles, $rootFolder) {
                // Memory limit beállítása
                ini_set('memory_limit', self::MAX_MEMORY_MB . 'M');

                // ZipStream létrehozása (v3.x verzió - egyszerűsített API)
                // enableZip64: automatikus nagy fájlok esetén
                // defaultCompressionMethod: Store = nincs tömörítés (gyorsabb)
                $zip = new ZipStream(
                    enableZip64: true,
                    defaultCompressionMethod: \ZipStream\CompressionMethod::STORE,
                    sendHttpHeaders: false, // Laravel kezeli a header-eket
                    flushOutput: true // Azonnal flush-öl a memory efficiency érdekében
                );

                // Progress update: processing started
                $this->updateProgress($progressKey, [
                    'status' => 'processing',
                    'processed' => 0,
                    'total' => $totalFiles,
                    'percentage' => 0
                ]);

                // Fájlok hozzáadása streaming módon
                foreach ($job->media as $index => $media) {
                    try {
                        $this->addFileToZip($zip, $media, $progressKey, $index, $totalFiles, $rootFolder);
                        $processedFiles++;

                        // Progress update minden fájl után
                        $percentage = round(($processedFiles / $totalFiles) * 100);
                        $this->updateProgress($progressKey, [
                            'status' => 'processing',
                            'processed' => $processedFiles,
                            'total' => $totalFiles,
                            'percentage' => $percentage,
                            'current_file' => $media->getOriginalFilename()
                        ]);

                        // Memory cleanup minden 10. fájl után
                        if ($processedFiles % 10 === 0) {
                            gc_collect_cycles();
                        }

                    } catch (\Exception $e) {
                        Log::error('ZIP streaming error for file', [
                            'media_id' => $media->id,
                            'error' => $e->getMessage()
                        ]);

                        // Hiba esetén folytatjuk a következő fájllal
                        continue;
                    }
                }

                // ZIP lezárása
                $zip->finish();

                // Final progress update
                $this->updateProgress($progressKey, [
                    'status' => 'completed',
                    'processed' => $processedFiles,
                    'total' => $totalFiles,
                    'percentage' => 100
                ]);

                // Memory cleanup
                gc_collect_cycles();

            },
            $zipFileName,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Accel-Buffering' => 'no', // Nginx buffering kikapcsolása
                'X-Content-Stream' => 'true' // Custom header jelzés a streaming-hez
            ]
        );
    }

    /**
     * Add file to ZIP stream with memory-efficient reading
     */
    protected function addFileToZip(
        ZipStream $zip,
        $media,
        string $progressKey,
        int $currentIndex,
        int $totalFiles,
        string $rootFolder = ''
    ): void {
        $mediaFile = $media->getFirstMedia('image_conversion');

        if (!$mediaFile) {
            return;
        }

        // A ConvertImageBatchJob az eredeti fájlt cseréli ki a konvertáltra,
        // ezért a fő fájlt kell használni (nem 'original_converted' konverziót)
        $filePath = $mediaFile->getPath();

        if (!file_exists($filePath)) {
            throw new FileNotFoundException("File not found: {$filePath}");
        }

        // Eredeti fájlnév
        $originalName = $mediaFile->getCustomProperty('original_name') ?? $mediaFile->file_name;

        // Replace extension with .jpg since all files are converted to JPEG
        $originalName = preg_replace('/\.(heic|heif|webp|avif|jxl|dng|cr2|nef|arw|orf|rw2|png|bmp)$/i', '.jpg', $originalName);

        // ZIP belső útvonal (mappa struktúrával, root folder-rel)
        $zipPath = $this->buildZipPath($rootFolder, $media->folder_path, $originalName);

        // Fájl hozzáadása streaming módon (v3.x API)
        $fileStream = fopen($filePath, 'rb');

        if (!$fileStream) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            // Stream fájl tartalom a ZIP-be chunk-onként
            // ZipStream v3 automatikusan kezeli a streaming-et
            $zip->addFileFromStream(
                fileName: $zipPath,
                stream: $fileStream,
                compressionMethod: \ZipStream\CompressionMethod::STORE, // Nincs tömörítés
                lastModificationDateTime: new \DateTimeImmutable($mediaFile->created_at)
            );

        } finally {
            // Mindig zárjuk be a stream-et
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
        }
    }

    /**
     * Build ZIP internal path with folder structure
     * Root folder is always the sanitized job_name
     * Inner folder paths keep their original names (with accents)
     */
    protected function buildZipPath(string $rootFolder, ?string $folderPath, string $filename): string
    {
        // Tisztítás és normalizálás a fájlnévre
        $filename = $this->sanitizeFileName($filename);

        // Folder path marad az eredeti (ékezetekkel, zárójelekkel)
        if ($folderPath) {
            $folderPath = trim($folderPath, '/\\');
            return $rootFolder . '/' . $folderPath . '/' . $filename;
        }

        return $rootFolder . '/' . $filename;
    }

    /**
     * Sanitize path/folder name: remove accents, parentheses, special chars
     */
    protected function sanitizePathName(string $name): string
    {
        // Remove accents
        $name = strtr($name, self::ACCENT_MAP);

        // Remove parentheses (keep content): "(11)" -> "11"
        $name = str_replace(['(', ')'], '', $name);

        // Replace special characters, keep alphanumeric, space, dash, underscore, slash, dot
        $name = preg_replace('/[^a-zA-Z0-9\s\-_\/.]/', '', $name);

        // Replace multiple spaces/underscores with single underscore
        $name = preg_replace('/[\s_]+/', '_', $name);

        // Trim underscores from start/end
        return trim($name, '_');
    }

    /**
     * Sanitize filename for ZIP archive
     */
    protected function sanitizeFileName(string $filename): string
    {
        // Eltávolítjuk a veszélyes karaktereket
        $filename = str_replace(['..', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $filename);

        // Többszörös slash-ek eltávolítása
        $filename = preg_replace('#/+#', '/', $filename);

        return trim($filename, '/');
    }

    /**
     * Generate ZIP filename
     */
    protected function generateZipFileName(ConversionJob $job): string
    {
        $baseName = $job->job_name ?: 'converted_images';
        $timestamp = now()->format('Y-m-d_His');

        // Tisztítás spec karakterektől
        $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);

        return "{$baseName}_{$timestamp}.zip";
    }

    /**
     * Calculate estimated ZIP size
     */
    protected function calculateEstimatedSize(ConversionJob $job): int
    {
        $totalSize = 0;

        foreach ($job->media as $media) {
            $mediaFile = $media->getFirstMedia('image_conversion');

            if (!$mediaFile) {
                continue;
            }

            // A ConvertImageBatchJob az eredeti fájlt cseréli ki a konvertáltra
            $filePath = $mediaFile->getPath();

            if (file_exists($filePath)) {
                $totalSize += filesize($filePath);
            }
        }

        // ZIP overhead hozzáadása (~1%)
        return (int)($totalSize * 1.01);
    }

    /**
     * Get progress cache key
     */
    public function getProgressCacheKey(ConversionJob $job): string
    {
        return "zip_generation_progress_{$job->id}";
    }

    /**
     * Update progress in cache
     */
    protected function updateProgress(string $key, array $data): void
    {
        $current = Cache::get($key, []);
        $updated = array_merge($current, $data);
        $updated['updated_at'] = now()->toIso8601String();

        Cache::put($key, $updated, now()->addMinutes(self::PROGRESS_CACHE_TTL));
    }

    /**
     * Get current progress
     */
    public function getProgress(ConversionJob $job): array
    {
        $key = $this->getProgressCacheKey($job);

        return Cache::get($key, [
            'status' => 'idle',
            'processed' => 0,
            'total' => 0,
            'percentage' => 0,
            'current_file' => null,
            'bytes_written' => 0,
            'estimated_size' => 0,
            'updated_at' => null
        ]);
    }

    /**
     * Clear progress cache
     */
    public function clearProgress(ConversionJob $job): void
    {
        Cache::forget($this->getProgressCacheKey($job));
    }

    /**
     * Check if ZipStream is available
     */
    public function isStreamingAvailable(): bool
    {
        return class_exists(\ZipStream\ZipStream::class);
    }

    /**
     * Get memory usage info for monitoring
     */
    public function getMemoryInfo(): array
    {
        return [
            'current' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
            'peak' => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
            'limit' => ini_get('memory_limit'),
            'percentage' => round((memory_get_usage(true) / $this->getMemoryLimitBytes()) * 100, 2)
        ];
    }

    /**
     * Get memory limit in bytes
     */
    protected function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit == -1) {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $bytes = (int)$limit;

        switch($unit) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return $bytes;
    }
}