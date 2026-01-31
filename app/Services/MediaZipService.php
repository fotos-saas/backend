<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Általános ZIP kicsomagolás és média feltöltés service.
 *
 * Újrahasználható bármely HasMedia modellel.
 */
class MediaZipService
{
    /**
     * Támogatott képformátumok
     */
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Maximum ZIP méret kicsomagolva (500MB)
     */
    private const MAX_EXTRACTED_SIZE = 524288000;

    /**
     * Maximum kép méret (20MB)
     */
    private const MAX_IMAGE_SIZE = 20971520;

    /**
     * Kihagyandó fájlok és mappák
     */
    private const HIDDEN_PATTERNS = ['__MACOSX', '.DS_Store', 'Thumbs.db', 'desktop.ini', '.git', '.svn'];

    /**
     * ZIP fájl kicsomagolása és képek feltöltése egy HasMedia modellhez.
     *
     * @param  UploadedFile  $zipFile  ZIP fájl
     * @param  HasMedia  $model  Célmodell (pl. PartnerAlbum)
     * @param  string  $collection  Média collection neve
     * @return Collection<Media> Feltöltött média rekordok
     */
    public function extractAndUpload(UploadedFile $zipFile, HasMedia $model, string $collection = 'photos'): Collection
    {
        $zip = new ZipArchive;
        $result = $zip->open($zipFile->getRealPath());

        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült megnyitni a ZIP fájlt. Hibakód: ' . $result);
        }

        $uploadedMedia = collect();
        $totalExtractedSize = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat['name'];

                // Security és szűrés
                if ($this->shouldSkipEntry($entryName)) {
                    continue;
                }

                // ZIP bomb védelem
                $totalExtractedSize += $stat['size'];
                if ($totalExtractedSize > self::MAX_EXTRACTED_SIZE) {
                    throw new \RuntimeException('A ZIP fájl túl nagy (max 500MB kicsomagolva)');
                }

                // Fájl méret ellenőrzés
                if ($stat['size'] > self::MAX_IMAGE_SIZE) {
                    Log::warning('MediaZip: Kép túl nagy, kihagyva', [
                        'entry' => $entryName,
                        'size' => $stat['size'],
                    ]);
                    continue;
                }

                // Kicsomagolás
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                // Temp fájl létrehozása
                $tempPath = sys_get_temp_dir() . '/' . uniqid('zip_') . '_' . basename($entryName);
                file_put_contents($tempPath, $content);

                try {
                    $uploadedFile = new UploadedFile(
                        $tempPath,
                        basename($entryName),
                        mime_content_type($tempPath) ?: 'application/octet-stream',
                        null,
                        true // test mode
                    );

                    // Valódi kép ellenőrzés
                    if (!$this->isValidImage($uploadedFile)) {
                        continue;
                    }

                    // Feltöltés
                    $media = $model
                        ->addMedia($uploadedFile)
                        ->preservingOriginal()
                        ->toMediaCollection($collection);

                    $uploadedMedia->push($media);
                } finally {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                }
            }
        } finally {
            $zip->close();
        }

        Log::info('MediaZip: Extraction completed', [
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'uploaded_count' => $uploadedMedia->count(),
            'zip_name' => $zipFile->getClientOriginalName(),
        ]);

        return $uploadedMedia;
    }

    /**
     * ZIP fájl-e ellenőrzés
     */
    public function isZipFile(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])
            || $extension === 'zip';
    }

    /**
     * Entry kihagyandó-e
     */
    protected function shouldSkipEntry(string $entryName): bool
    {
        // Könyvtárak
        if (str_ends_with($entryName, '/')) {
            return true;
        }

        // Path traversal védelem
        $normalized = str_replace('\\', '/', $entryName);
        if (str_contains($normalized, '../') || str_starts_with($normalized, '/')) {
            return true;
        }

        // Abszolút Windows útvonal
        if (preg_match('/^[a-zA-Z]:/', $normalized)) {
            return true;
        }

        // Hidden fájlok és mappák
        foreach (self::HIDDEN_PATTERNS as $pattern) {
            if (str_contains($entryName, $pattern)) {
                return true;
            }
        }

        // Ponttal kezdődő fájlok
        $basename = basename($entryName);
        if (str_starts_with($basename, '.') && $basename !== '.') {
            return true;
        }

        // Nem képfájl
        $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

        return !in_array($extension, self::SUPPORTED_EXTENSIONS);
    }

    /**
     * Valódi kép ellenőrzés
     */
    protected function isValidImage(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS)) {
            return false;
        }

        // MIME type ellenőrzés
        $mimeType = $file->getMimeType();
        $validMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $validMimes)) {
            return false;
        }

        // Valódi kép ellenőrzés - getimagesize() MIME spoofing ellen
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            return false;
        }

        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        return in_array($imageInfo[2], $allowedImageTypes, true);
    }
}
