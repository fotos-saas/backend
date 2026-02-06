<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FileValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    public const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;

    public const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public const VIDEO_MIMES = [
        'video/mp4',
        'video/webm',
    ];

    public const ARCHIVE_EXTENSIONS = ['zip', 'rar', '7z'];

    private const IMAGE_MAGIC_BYTES = [
        'jpeg' => "\xFF\xD8\xFF",
        'bmp' => 'BM',
    ];

    private const ARCHIVE_MAGIC_BYTES = [
        'zip' => "PK\x03\x04",
        'rar' => 'Rar!',
        '7z' => "7z\xBC\xAF\x27\x1C",
    ];

    /**
     * Fájl feltöltése UUID névvel.
     */
    public function store(
        UploadedFile $file,
        string $directory,
        string $disk = 'public'
    ): StoredFileResult {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $storedPath = $file->storeAs($directory, $filename, $disk);

        return new StoredFileResult(
            path: $storedPath,
            filename: $filename,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            disk: $disk,
        );
    }

    /**
     * Fájl feltöltése biztonságos névvel (slug + timestamp + random).
     */
    public function storeWithSafeName(
        UploadedFile $file,
        string $directory,
        string $disk = 'public'
    ): StoredFileResult {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $this->generateSafeFilename($file->getClientOriginalName(), $extension);

        $storedPath = $file->storeAs($directory, $filename, $disk);

        return new StoredFileResult(
            path: $storedPath,
            filename: $filename,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            disk: $disk,
        );
    }

    /**
     * Lokális fájlt storage-ra másol (pl. generált ZIP).
     */
    public function storeFromLocalPath(
        string $localPath,
        string $storagePath,
        string $disk = 'local'
    ): void {
        Storage::disk($disk)->put($storagePath, file_get_contents($localPath));
    }

    /**
     * MIME + méret validáció.
     *
     * @throws FileValidationException
     */
    public function validate(
        UploadedFile $file,
        array $allowedMimes = self::IMAGE_MIMES,
        int $maxSize = self::DEFAULT_MAX_SIZE
    ): void {
        if ($file->getSize() > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            throw FileValidationException::tooLarge($maxMB);
        }

        if (! in_array($file->getMimeType(), $allowedMimes)) {
            throw FileValidationException::invalidMimeType();
        }
    }

    /**
     * Validáció + feltöltés egyben.
     *
     * @throws FileValidationException
     */
    public function validateAndStore(
        UploadedFile $file,
        string $directory,
        array $allowedMimes = self::IMAGE_MIMES,
        int $maxSize = self::DEFAULT_MAX_SIZE,
        string $disk = 'public'
    ): StoredFileResult {
        $this->validate($file, $allowedMimes, $maxSize);

        return $this->store($file, $directory, $disk);
    }

    /**
     * Kép validáció: MIME + getimagesize + opcionális magic bytes.
     *
     * @throws FileValidationException
     */
    public function validateImage(
        UploadedFile $file,
        array $allowedExtensions = ['jpg', 'jpeg', 'bmp'],
        int $maxSize = 16 * 1024 * 1024,
        bool $checkMagicBytes = false
    ): void {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw FileValidationException::invalidExtension();
        }

        if ($file->getSize() > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            throw FileValidationException::tooLarge($maxMB);
        }

        if ($checkMagicBytes && ! $this->checkImageMagicBytes($file)) {
            throw FileValidationException::magicBytesMismatch();
        }
    }

    /**
     * Archívum validáció: kiterjesztés + magic bytes.
     *
     * @throws FileValidationException
     */
    public function validateArchive(
        UploadedFile $file,
        array $allowedExtensions = self::ARCHIVE_EXTENSIONS
    ): void {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw FileValidationException::invalidExtension();
        }

        if (! $this->checkArchiveMagicBytes($file)) {
            throw FileValidationException::magicBytesMismatch();
        }
    }

    /**
     * Fájl törlés.
     */
    public function delete(string $filePath, string $disk = 'public'): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $relativePath = str_starts_with($filePath, '/storage/')
            ? str_replace('/storage/', '', $filePath)
            : $filePath;

        return Storage::disk($disk)->delete($relativePath);
    }

    /**
     * Üres mappa törlés.
     */
    public function cleanupEmptyDirectory(string $directory, string $disk = 'public'): void
    {
        if (Storage::disk($disk)->exists($directory)) {
            $files = Storage::disk($disk)->files($directory);
            if (empty($files)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }
    }

    /**
     * Kép méretei.
     *
     * @return array{width: int|null, height: int|null}
     */
    public function getImageDimensions(UploadedFile $file): array
    {
        if (! str_starts_with($file->getMimeType(), 'image/')) {
            return ['width' => null, 'height' => null];
        }

        $dimensions = getimagesize($file->getPathname());
        if (! $dimensions) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => $dimensions[0], 'height' => $dimensions[1]];
    }

    /**
     * Storage URL.
     */
    public function url(string $path, string $disk = 'public'): string
    {
        return Storage::disk($disk)->url($path);
    }

    /**
     * Biztonságos fájlnév generálás (slug + timestamp + random).
     */
    public function generateSafeFilename(string $originalName, string $extension): string
    {
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $safeName = substr($safeName, 0, 100);

        if (empty($safeName)) {
            $safeName = 'file';
        }

        $safeExtension = strtolower(preg_replace('/[^a-z0-9]/', '', $extension));

        return $safeName . '_' . time() . '_' . Str::random(8) . '.' . $safeExtension;
    }

    /**
     * Kép + videó MIME típusok összevonva.
     */
    public static function imageAndVideoMimes(): array
    {
        return array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES);
    }

    /**
     * Kép-e a fájl.
     */
    public function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Video-e a fájl.
     */
    public function isVideo(string $mimeType): bool
    {
        return in_array($mimeType, self::VIDEO_MIMES);
    }

    private function checkImageMagicBytes(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            return false;
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if (strlen($bytes) < 2) {
            return false;
        }

        foreach (self::IMAGE_MAGIC_BYTES as $magic) {
            if (str_starts_with($bytes, $magic)) {
                return true;
            }
        }

        return false;
    }

    private function checkArchiveMagicBytes(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            return false;
        }

        $bytes = fread($handle, 8);
        fclose($handle);

        if (strlen($bytes) < 4) {
            return false;
        }

        foreach (self::ARCHIVE_MAGIC_BYTES as $magic) {
            if (str_starts_with($bytes, $magic)) {
                return true;
            }
        }

        return false;
    }
}
