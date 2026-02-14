<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Helpers\StringHelper;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Iskola tanári aktív fotóinak ZIP exportja.
 *
 * Alapértelmezett struktúra (archive):
 *   {Iskola rövid név}/Tanár Név.jpg
 *
 * Összes projekt struktúra (all_projects):
 *   {Iskola rövid név}/{tanév - osztály}/Tanár Név.jpg
 */
class GenerateSchoolTeacherPhotosZipAction
{
    /** @var string[] IPTC temp fájlok */
    private array $tempFiles = [];

    public function execute(
        int $schoolId,
        int $partnerId,
        string $schoolName,
        string $fileNaming = 'student_name',
        bool $allProjects = false,
    ): string {
        $zip = new ZipArchive;
        $timestamp = now()->format('Y-m-d-His');
        $zipPath = sys_get_temp_dir() . "/teachers-{$schoolId}-{$timestamp}.zip";

        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült létrehozni a ZIP fájlt. Hibakód: ' . $result);
        }

        try {
            $rootFolder = StringHelper::abbreviateMiddle($this->sanitizeName($schoolName), 50);

            $addedCount = $allProjects
                ? $this->buildProjectBasedZip($zip, $rootFolder, $schoolId, $partnerId, $fileNaming)
                : $this->buildArchiveBasedZip($zip, $rootFolder, $schoolId, $partnerId, $fileNaming);

            if ($addedCount === 0) {
                $zip->addFromString("{$rootFolder}/info.txt", 'Nincsenek aktív tanári fotók ehhez az iskolához.');
            }

            $zip->close();
            $this->cleanupTempFiles();

            Log::info('GenerateSchoolTeacherPhotosZipAction: ZIP létrehozva', [
                'school_id' => $schoolId,
                'mode' => $allProjects ? 'all_projects' : 'archive',
                'added_files' => $addedCount,
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
     * Archív tanárok (jelenlegi aktív fotó): flat struktúra.
     */
    private function buildArchiveBasedZip(
        ZipArchive $zip,
        string $rootFolder,
        int $schoolId,
        int $partnerId,
        string $fileNaming,
    ): int {
        $teachers = TeacherArchive::forPartner($partnerId)
            ->forSchool($schoolId)
            ->active()
            ->whereNotNull('active_photo_id')
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        $addedCount = 0;
        $usedFilenames = [];

        foreach ($teachers as $teacher) {
            $media = $teacher->activePhoto;
            if (!$media) {
                continue;
            }

            [$filePath, $filename, $tempFile] = $this->resolveFile($media, $teacher->full_display_name, $fileNaming);
            if (!$filePath) {
                continue;
            }

            $filename = $this->resolveUniqueFilename($filename, $usedFilenames);
            $usedFilenames[] = $filename;

            $zip->addFile($filePath, "{$rootFolder}/{$filename}");

            if ($tempFile) {
                $this->tempFiles[] = $tempFile;
            }
            $addedCount++;
        }

        return $addedCount;
    }

    /**
     * Összes projekt tanárai: évenként mappázott struktúra.
     * {root}/{tanév - osztály}/tanárnév.jpg
     */
    private function buildProjectBasedZip(
        ZipArchive $zip,
        string $rootFolder,
        int $schoolId,
        int $partnerId,
        string $fileNaming,
    ): int {
        $projects = TabloProject::where('partner_id', $partnerId)
            ->where('school_id', $schoolId)
            ->orderByDesc('class_year')
            ->orderBy('class_name')
            ->get();

        $addedCount = 0;

        foreach ($projects as $project) {
            $projectFolder = $this->buildProjectFolderName($project);

            $teachers = TabloPerson::where('tablo_project_id', $project->id)
                ->where('type', 'teacher')
                ->with(['overridePhoto', 'teacherArchive.activePhoto', 'photo'])
                ->orderBy('name')
                ->get();

            $usedFilenames = [];

            foreach ($teachers as $person) {
                $media = $this->resolveEffectiveMedia($person);
                if (!$media) {
                    continue;
                }

                [$filePath, $filename, $tempFile] = $this->resolveFile($media, $person->name, $fileNaming);
                if (!$filePath) {
                    continue;
                }

                $filename = $this->resolveUniqueFilename($filename, $usedFilenames);
                $usedFilenames[] = $filename;

                $zip->addFile($filePath, "{$rootFolder}/{$projectFolder}/{$filename}");

                if ($tempFile) {
                    $this->tempFiles[] = $tempFile;
                }
                $addedCount++;
            }
        }

        return $addedCount;
    }

    /**
     * Effektív Media: override → archive.active_photo → legacy media_id
     */
    private function resolveEffectiveMedia(TabloPerson $person): ?Media
    {
        if ($person->override_photo_id) {
            return $person->overridePhoto;
        }

        if ($person->archive_id && $person->teacherArchive?->active_photo_id) {
            return $person->teacherArchive->activePhoto;
        }

        if ($person->media_id) {
            return $person->photo;
        }

        return null;
    }

    /**
     * Projekt mappa név: "2024-2025 - 12.A" vagy "12.A" vagy "Ismeretlen projekt"
     */
    private function buildProjectFolderName(TabloProject $project): string
    {
        $parts = [];
        if ($project->class_year) {
            $parts[] = $project->class_year;
        }
        if ($project->class_name) {
            $parts[] = $project->class_name;
        }

        $name = $parts ? implode(' - ', $parts) : "Projekt {$project->id}";

        return $this->sanitizeName($name);
    }

    /**
     * Fájl előkészítés: visszaadja [filePath, filename, tempFile].
     */
    private function resolveFile(Media $media, string $displayName, string $fileNaming): array
    {
        $originalPath = $media->getPath();
        if (!file_exists($originalPath)) {
            return [null, null, null];
        }

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $filePath = $originalPath;
        $tempFile = null;

        if ($fileNaming === 'original') {
            $filename = $media->file_name;
        } else {
            $filename = $this->sanitizeName($displayName) . ".{$extension}";

            if ($fileNaming === 'student_name_iptc') {
                $tempFile = tempnam(sys_get_temp_dir(), 'iptc_') . '.' . $extension;
                copy($originalPath, $tempFile);
                $this->embedIptcName($tempFile, $displayName);
                $filePath = $tempFile;
            }
        }

        return [$filePath, $filename, $tempFile];
    }

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
        $tag = chr(0x1C) . chr(0x02) . chr(0x05);
        $nameBytes = mb_convert_encoding($name, 'UTF-8');
        $iptcData .= $tag . pack('n', strlen($nameBytes)) . $nameBytes;

        $content = iptcembed($iptcData, $filePath);
        if ($content !== false) {
            @file_put_contents($filePath, $content);
        }
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name) ?? $name;
        return trim($name);
    }

    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
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
