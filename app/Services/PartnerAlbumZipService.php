<?php

namespace App\Services;

use App\Models\PartnerAlbum;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Partner Album ZIP export service.
 *
 * Kiválasztott képek ZIP archívumba csomagolása.
 */
class PartnerAlbumZipService
{
    /**
     * Kiválasztott képek ZIP-be csomagolása.
     *
     * @param  PartnerAlbum  $album  Album modell
     * @param  array<int>  $photoIds  Média ID-k
     * @return string  ZIP fájl teljes elérési útja (temp)
     *
     * @throws \RuntimeException  Ha nem sikerül a ZIP létrehozása
     */
    public function generateSelectedPhotosZip(PartnerAlbum $album, array $photoIds): string
    {
        $zip = new ZipArchive;
        $timestamp = now()->format('Y-m-d-His');
        $zipFileName = "album-{$album->id}-selected-{$timestamp}.zip";
        $zipPath = sys_get_temp_dir() . '/' . $zipFileName;

        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült létrehozni a ZIP fájlt. Hibakód: ' . $result);
        }

        try {
            $media = $album->getMedia('photos')->whereIn('id', $photoIds);
            $usedFilenames = [];
            $addedCount = 0;

            foreach ($media as $item) {
                $originalPath = $item->getPath();

                if (!file_exists($originalPath)) {
                    Log::warning('PartnerAlbumZipService: Fájl nem található', [
                        'media_id' => $item->id,
                        'path' => $originalPath,
                    ]);
                    continue;
                }

                $filename = $item->file_name;
                $uniqueFilename = $this->resolveUniqueFilename($filename, $usedFilenames);
                $usedFilenames[] = $uniqueFilename;

                $zip->addFile($originalPath, $uniqueFilename);
                $addedCount++;
            }

            $zip->close();

            Log::info('PartnerAlbumZipService: ZIP létrehozva', [
                'album_id' => $album->id,
                'requested_photos' => count($photoIds),
                'added_photos' => $addedCount,
                'zip_path' => $zipPath,
            ]);

            return $zipPath;
        } catch (\Exception $e) {
            $zip->close();

            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            throw $e;
        }
    }

    /**
     * Egyedi fájlnév biztosítása ütközés esetén.
     *
     * @param  string  $filename  Eredeti fájlnév
     * @param  array<string>  $usedFilenames  Már használt fájlnevek
     * @return string  Egyedi fájlnév
     */
    private function resolveUniqueFilename(string $filename, array $usedFilenames): string
    {
        if (!in_array($filename, $usedFilenames)) {
            return $filename;
        }

        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        $counter = 1;

        do {
            $newFilename = $extension
                ? "{$baseName}_{$counter}.{$extension}"
                : "{$baseName}_{$counter}";
            $counter++;
        } while (in_array($newFilename, $usedFilenames));

        return $newFilename;
    }
}
