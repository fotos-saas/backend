<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Helpers\StringHelper;
use App\Models\TeacherArchive;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Iskola tanári aktív fotóinak ZIP exportja.
 *
 * Struktúra:
 *   {Iskola rövid név}/
 *     Tanár Név.jpg (vagy eredeti fájlnév)
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

                $originalPath = $media->getPath();
                if (!file_exists($originalPath)) {
                    continue;
                }

                $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
                $filePath = $originalPath;
                $tempFile = null;

                if ($fileNaming === 'original') {
                    $filename = $media->file_name;
                } else {
                    $displayName = $teacher->full_display_name;
                    $filename = $this->sanitizeName($displayName) . ".{$extension}";

                    if ($fileNaming === 'student_name_iptc') {
                        $tempFile = tempnam(sys_get_temp_dir(), 'iptc_') . '.' . $extension;
                        copy($originalPath, $tempFile);
                        $this->embedIptcName($tempFile, $displayName);
                        $filePath = $tempFile;
                    }
                }

                $filename = $this->resolveUniqueFilename($filename, $usedFilenames);
                $usedFilenames[] = $filename;

                $zip->addFile($filePath, "{$rootFolder}/{$filename}");

                if ($tempFile) {
                    $this->tempFiles[] = $tempFile;
                }

                $addedCount++;
            }

            if ($addedCount === 0) {
                $zip->addFromString("{$rootFolder}/info.txt", 'Nincsenek aktív tanári fotók ehhez az iskolához.');
            }

            $zip->close();
            $this->cleanupTempFiles();

            Log::info('GenerateSchoolTeacherPhotosZipAction: ZIP létrehozva', [
                'school_id' => $schoolId,
                'teachers' => $teachers->count(),
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
