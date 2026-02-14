<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGuestSession;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TabloUserProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Galéria monitoring ZIP export.
 *
 * Struktúra:
 *   {Projekt név}/
 *     {Diák név}/
 *       retusalt/
 *       tablokep/
 *       osszes/
 *     export.xlsx (opcionális)
 */
class GenerateGalleryZipAction
{
    /** @var string[] IPTC temp fájlok listája - ZIP lezárás után törlendők */
    private array $tempFiles = [];

    /**
     * ZIP generálása.
     *
     * @param  array<int>|null  $personIds  Szűrt személy ID-k (null = mindenki)
     * @param  string  $zipContent  retouch_only|tablo_only|all|retouch_and_tablo
     * @param  string  $fileNaming  original|student_name|student_name_iptc
     * @param  string|null  $excelPath  Excel fájl elérési útja (ha mellékelni kell)
     * @return string  Temp ZIP fájl elérési útja
     */
    public function execute(
        TabloProject $project,
        int $galleryId,
        ?array $personIds,
        string $zipContent = 'all',
        string $fileNaming = 'original',
        ?string $excelPath = null,
        ?string $personType = null,
    ): string {
        $zip = new ZipArchive;
        $timestamp = now()->format('Y-m-d-His');
        $zipFileName = "gallery-{$project->id}-{$timestamp}.zip";
        $zipPath = sys_get_temp_dir() . '/' . $zipFileName;

        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült létrehozni a ZIP fájlt. Hibakód: ' . $result);
        }

        try {
            $projectFolder = $this->sanitizeFolderName($project->name) . " ({$project->id})";
            $addedCount = 0;

            // Személyek és progress adatok lekérése
            $personsQuery = TabloPerson::where('tablo_project_id', $project->id)
                ->orderBy('name');

            if ($personIds !== null && count($personIds) > 0) {
                $personsQuery->whereIn('id', $personIds);
            }

            if ($personType !== null) {
                $personsQuery->where('type', $personType);
            }

            $persons = $personsQuery->get();

            $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)
                ->get()
                ->keyBy('user_id');

            $guestSessions = TabloGuestSession::where('tablo_project_id', $project->id)
                ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
                ->whereNotNull('tablo_person_id')
                ->get()
                ->keyBy('tablo_person_id');

            $gallery = $project->gallery;

            foreach ($persons as $person) {
                $session = $guestSessions->get($person->id);
                $progress = $this->findProgress($session, $progressRecords);

                if (!$progress) {
                    continue;
                }

                $personFolder = "{$projectFolder}/" . $this->sanitizeFolderName($person->name);
                $added = $this->addPersonPhotos(
                    $zip, $gallery, $progress, $person, $personFolder,
                    $zipContent, $fileNaming,
                );
                $addedCount += $added;
            }

            // Excel mellékelés
            if ($excelPath && file_exists($excelPath)) {
                $zip->addFile($excelPath, "{$projectFolder}/export.xlsx");
            }

            $zip->close();
            $this->cleanupTempFiles();

            Log::info('GenerateGalleryZipAction: ZIP létrehozva', [
                'project_id' => $project->id,
                'persons' => $persons->count(),
                'added_files' => $addedCount,
                'zip_path' => $zipPath,
            ]);

            return $zipPath;
        } catch (\Exception $e) {
            $zip->close();
            $this->cleanupTempFiles();
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            throw $e;
        }
    }

    /**
     * Egy személy képeinek hozzáadása a ZIP-hez.
     */
    private function addPersonPhotos(
        ZipArchive $zip,
        $gallery,
        TabloUserProgress $progress,
        TabloPerson $person,
        string $personFolder,
        string $zipContent,
        string $fileNaming,
    ): int {
        $addedCount = 0;
        $usedFilenames = [];
        $stepsData = $progress->steps_data ?? [];

        // Retusált képek
        if (in_array($zipContent, ['retouch_only', 'retouch_and_tablo', 'all'])) {
            $retouchIds = $stepsData['retouch_media_ids'] ?? [];
            if (!empty($retouchIds)) {
                $retouchMedia = Media::whereIn('id', $retouchIds)->get();
                $subfolder = "{$personFolder}/retusalt";
                $counter = 1;

                foreach ($retouchMedia as $media) {
                    $addedCount += $this->addMediaToZip(
                        $zip, $media, $subfolder, $person->name, 'retusalt',
                        $counter, $fileNaming, $usedFilenames,
                    );
                    $counter++;
                }
            }
        }

        // Tablókép
        if (in_array($zipContent, ['tablo_only', 'retouch_and_tablo', 'all'])) {
            $tabloMediaId = $stepsData['tablo_media_id'] ?? null;
            if ($tabloMediaId) {
                $tabloMedia = Media::find($tabloMediaId);
                if ($tabloMedia) {
                    $subfolder = "{$personFolder}/tablokep";
                    $addedCount += $this->addMediaToZip(
                        $zip, $tabloMedia, $subfolder, $person->name, 'tablokep',
                        1, $fileNaming, $usedFilenames,
                    );
                }
            }
        }

        // Összes saját kép (csak 'all' módban)
        if ($zipContent === 'all') {
            $claimedIds = $stepsData['claimed_media_ids'] ?? [];
            if (!empty($claimedIds)) {
                $claimedMedia = Media::whereIn('id', $claimedIds)->get();
                $subfolder = "{$personFolder}/osszes";
                $counter = 1;

                foreach ($claimedMedia as $media) {
                    $addedCount += $this->addMediaToZip(
                        $zip, $media, $subfolder, $person->name, 'osszes',
                        $counter, $fileNaming, $usedFilenames,
                    );
                    $counter++;
                }
            }
        }

        return $addedCount;
    }

    /**
     * Média fájl hozzáadása a ZIP-hez.
     */
    private function addMediaToZip(
        ZipArchive $zip,
        Media $media,
        string $subfolder,
        string $personName,
        string $category,
        int $counter,
        string $fileNaming,
        array &$usedFilenames,
    ): int {
        $originalPath = $media->getPath();
        if (!file_exists($originalPath)) {
            Log::warning('GenerateGalleryZipAction: Fájl nem található', [
                'media_id' => $media->id,
                'path' => $originalPath,
            ]);
            return 0;
        }

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';

        $filePath = $originalPath;
        $tempFile = null;

        switch ($fileNaming) {
            case 'student_name':
                $filename = "{$personName}_{$category}_{$counter}.{$extension}";
                break;
            case 'student_name_iptc':
                $filename = $media->file_name;
                // IPTC-t temp fájlba ágyazzuk, NE az eredetibe!
                $tempFile = tempnam(sys_get_temp_dir(), 'iptc_') . '.' . $extension;
                copy($originalPath, $tempFile);
                $this->embedIptcName($tempFile, $personName);
                $filePath = $tempFile;
                break;
            default: // 'original'
                $filename = $media->file_name;
                break;
        }

        // Fájlnév ütközés kezelése
        $filename = $this->resolveUniqueFilename($filename, $usedFilenames);
        $usedFilenames[] = $filename;

        $zip->addFile($filePath, "{$subfolder}/{$filename}");

        // Temp fájl törlés a ZIP lezárása után kell, ezért regisztráljuk
        if ($tempFile) {
            $this->tempFiles[] = $tempFile;
        }

        return 1;
    }

    /**
     * IPTC Object Name beágyazása (csak JPEG-re működik).
     */
    private function embedIptcName(string $filePath, string $name): void
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg'])) {
            return;
        }

        $info = [];
        $size = @getimagesize($filePath, $info);
        if ($size === false) {
            return;
        }

        $iptcData = '';
        // 2#005 = Object Name
        $tag = chr(0x1C) . chr(0x02) . chr(0x05);
        $nameBytes = mb_convert_encoding($name, 'UTF-8');
        $iptcData .= $tag . pack('n', strlen($nameBytes)) . $nameBytes;

        $content = iptcembed($iptcData, $filePath);
        if ($content !== false) {
            @file_put_contents($filePath, $content);
        }
    }

    private function findProgress(?TabloGuestSession $session, Collection $progressRecords): ?TabloUserProgress
    {
        if (!$session || !$session->user_id) {
            return null;
        }

        return $progressRecords->get($session->user_id);
    }

    private function sanitizeFolderName(string $name): string
    {
        // Illegális karakterek cseréje (Windows + Unix kompatibilis)
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name) ?? $name;
        return trim($name);
    }

    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            // tempnam() által létrehozott eredeti fájl (kiterjesztés nélkül) törlése
            $withoutExt = preg_replace('/\.\w+$/', '', $tempFile);
            if ($withoutExt !== $tempFile && file_exists($withoutExt)) {
                @unlink($withoutExt);
            }
        }
        $this->tempFiles = [];
    }

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
