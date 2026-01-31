<?php

namespace App\Services\Tablo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tablo Media Service
 *
 * Közös média kezelési logika:
 * - Fájl feltöltés
 * - Fájl validáció
 * - Fájl törlés
 * - Üres mappák takarítása
 *
 * Használat:
 *   $this->mediaService->upload($post, $file, 'tablo-newsfeed', $projectId);
 *   $this->mediaService->delete($media);
 */
class TabloMediaService
{
    /**
     * Alapértelmezett maximum méret (10MB)
     */
    public const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;

    /**
     * Alapértelmezett engedélyezett MIME típusok
     */
    public const DEFAULT_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Video MIME típusok
     */
    public const VIDEO_MIMES = [
        'video/mp4',
        'video/webm',
    ];

    /**
     * Fájl feltöltése storage-ra
     *
     * @param  string  $directory  Alap könyvtár (pl. 'tablo-newsfeed', 'tablo-discussions')
     * @param  int|string  $subdirectory  Alkönyvtár (általában project_id)
     * @return array{path: string, filename: string, original_name: string, mime_type: string, size: int, is_image: bool}
     */
    public function store(
        UploadedFile $file,
        string $directory,
        int|string $subdirectory,
        string $disk = 'public'
    ): array {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $subdirectory;

        $storedPath = $file->storeAs($path, $filename, $disk);

        return [
            'path' => $storedPath,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_image' => str_starts_with($file->getMimeType(), 'image/'),
        ];
    }

    /**
     * Fájl törlése storage-ról
     */
    public function deleteFile(string $filePath, string $disk = 'public'): bool
    {
        if (empty($filePath)) {
            return false;
        }

        // Ha /storage/ prefix van, levágjuk
        $relativePath = str_starts_with($filePath, '/storage/')
            ? str_replace('/storage/', '', $filePath)
            : $filePath;

        return Storage::disk($disk)->delete($relativePath);
    }

    /**
     * Üres mappa törlése
     */
    public function cleanupEmptyFolder(string $folder, string $disk = 'public'): void
    {
        if (Storage::disk($disk)->exists($folder)) {
            $files = Storage::disk($disk)->files($folder);
            if (empty($files)) {
                Storage::disk($disk)->deleteDirectory($folder);
            }
        }
    }

    /**
     * Fájl validáció
     *
     * @param  array  $allowedMimes  Engedélyezett MIME típusok
     * @param  int  $maxSize  Maximum méret byte-ban
     *
     * @throws \InvalidArgumentException
     */
    public function validate(
        UploadedFile $file,
        array $allowedMimes = self::DEFAULT_ALLOWED_MIMES,
        int $maxSize = self::DEFAULT_MAX_SIZE
    ): void {
        if ($file->getSize() > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            throw new \InvalidArgumentException("A fájl túl nagy (max {$maxMB}MB).");
        }

        if (! in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('Nem engedélyezett fájltípus.');
        }
    }

    /**
     * Validáció és feltöltés egyben
     *
     * @return array{path: string, filename: string, original_name: string, mime_type: string, size: int, is_image: bool}
     *
     * @throws \InvalidArgumentException
     */
    public function validateAndStore(
        UploadedFile $file,
        string $directory,
        int|string $subdirectory,
        array $allowedMimes = self::DEFAULT_ALLOWED_MIMES,
        int $maxSize = self::DEFAULT_MAX_SIZE,
        string $disk = 'public'
    ): array {
        $this->validate($file, $allowedMimes, $maxSize);

        return $this->store($file, $directory, $subdirectory, $disk);
    }

    /**
     * Több fájl feltöltése
     *
     * @param  UploadedFile[]  $files
     * @return array[]
     */
    public function storeMultiple(
        array $files,
        string $directory,
        int|string $subdirectory,
        array $allowedMimes = self::DEFAULT_ALLOWED_MIMES,
        int $maxSize = self::DEFAULT_MAX_SIZE,
        string $disk = 'public'
    ): array {
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->validateAndStore($file, $directory, $subdirectory, $allowedMimes, $maxSize, $disk);
        }

        return $results;
    }

    /**
     * Média model törlése fájllal együtt
     *
     * @param  Model  $media  A média model (TabloNewsfeedMedia, TabloPostMedia, TabloPollMedia)
     * @param  string  $filePathAttribute  A fájl útvonalat tartalmazó attribútum neve
     */
    public function deleteMediaWithFile(
        Model $media,
        string $filePathAttribute = 'file_path',
        string $disk = 'public'
    ): void {
        $filePath = $media->{$filePathAttribute} ?? null;

        if ($filePath) {
            $this->deleteFile($filePath, $disk);
        }

        $media->delete();

        Log::info('Media deleted', [
            'media_class' => get_class($media),
            'media_id' => $media->id ?? 'unknown',
        ]);
    }

    /**
     * Kép-e a fájl
     */
    public function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Video-e a fájl
     */
    public function isVideo(string $mimeType): bool
    {
        return in_array($mimeType, self::VIDEO_MIMES);
    }

    /**
     * Engedélyezett képek és videók MIME típusai
     */
    public static function imageAndVideoMimes(): array
    {
        return array_merge(self::DEFAULT_ALLOWED_MIMES, self::VIDEO_MIMES);
    }
}
