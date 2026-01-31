<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\TabloRegistration;
use App\Models\TabloUserProgress;
use App\Models\WorkSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class WorkSessionZipService
{
    /**
     * Generate ZIP file containing all photos from work session albums
     *
     * @param  WorkSession  $workSession  The work session to download albums from
     * @param  array|null  $albumIds  Optional array of album IDs to download (null = all albums)
     * @return string Path to the generated ZIP file
     *
     * @throws \Exception
     */
    public function generateAlbumsZip(WorkSession $workSession, ?array $albumIds = null): string
    {
        $zip = new ZipArchive;
        $zipFileName = $this->generateZipFileName($workSession);

        // Use system temp directory (guaranteed writable)
        $zipPath = sys_get_temp_dir().'/'.$zipFileName;

        // Create ZIP archive
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create ZIP file');
        }

        $albums = $workSession->albums()
            ->with('photos.media')
            ->when($albumIds, fn ($query) => $query->whereIn('albums.id', $albumIds))
            ->get();

        if ($albums->isEmpty()) {
            $zip->close();
            throw new \Exception('No albums found for this work session');
        }

        // Create root folder inside ZIP with Work Session name
        $workSessionFolder = $this->sanitizeFolderName(
            "{$workSession->id} - ".($workSession->name ?: 'work-session')
        );

        $totalPhotos = 0;

        foreach ($albums as $album) {
            $photos = $album->photos;

            // Generate album folder name with ID
            $albumFolderName = $this->sanitizeFolderName(
                "{$album->id} - ".($album->name ?: 'Album')
            );

            // Create album folder (even if empty)
            $albumFolderPath = $workSessionFolder.'/'.$albumFolderName;
            $zip->addEmptyDir($albumFolderPath);

            // If album has no photos, skip to next album
            if ($photos->isEmpty()) {
                continue;
            }

            $usedFilenames = []; // Track used filenames within album

            foreach ($photos as $photo) {
                $media = $photo->getFirstMedia('photo');

                if (! $media) {
                    continue; // Skip photos without media
                }

                // Get original file path
                $originalPath = $media->getPath();

                if (! file_exists($originalPath)) {
                    continue; // Skip if file doesn't exist
                }

                // Get original filename from custom property
                $originalFilename = $media->getCustomProperty('original_filename');

                // Fallback to photo ID if no original filename
                if (! $originalFilename) {
                    $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
                    $originalFilename = "photo-{$photo->id}.{$extension}";
                }

                // Handle duplicate filenames
                $uniqueFilename = $this->resolveUniqueFilename($originalFilename, $usedFilenames);
                $usedFilenames[] = $uniqueFilename;

                // Add file to ZIP under work session folder / album folder
                $zipFilePath = $workSessionFolder.'/'.$albumFolderName.'/'.$uniqueFilename;
                $zip->addFile($originalPath, $zipFilePath);

                $totalPhotos++;
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Generate ZIP file based on user selections (Download Manager)
     *
     * @param  WorkSession  $workSession  The work session
     * @param  array  $userIds  Array of user IDs to include
     * @param  string  $photoType  Type of photos: 'claimed' | 'retus' | 'tablo'
     * @param  string  $filenameMode  Filename mode: 'original' | 'user_name' | 'original_exif'
     * @param  string|null  $downloadId  Optional download ID for progress tracking
     * @return string Path to the generated ZIP file
     *
     * @throws \Exception
     */
    public function generateManagerZip(
        WorkSession $workSession,
        array $userIds,
        string $photoType,
        string $filenameMode,
        ?string $downloadId = null
    ): string {
        $zip = new ZipArchive;
        $zipFileName = $this->generateZipFileName($workSession);
        $zipPath = sys_get_temp_dir().'/'.$zipFileName;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create ZIP file');
        }

        // Root folder
        $workSessionFolder = $this->sanitizeFolderName(
            "{$workSession->id} - ".($workSession->name ?: 'work-session')
        );

        $processedUsers = 0;
        $tempFiles = []; // Track temp files for cleanup

        // Initialize progress tracking if downloadId is provided
        if ($downloadId) {
            Cache::put("download_progress:{$downloadId}", [
                'current' => 0,
                'total' => count($userIds),
                'status' => 'processing',
            ], now()->addMinutes(10));
        }

        // Check if ANY user has school_class_id in tablo_registrations
        $registrations = TabloRegistration::whereIn('user_id', $userIds)
            ->where('work_session_id', $workSession->id)
            ->with('schoolClass')
            ->get()
            ->keyBy('user_id');

        $useSchoolClassGrouping = $registrations->contains(fn($reg) => !is_null($reg->school_class_id));

        foreach ($userIds as $userId) {
            $user = \App\Models\User::find($userId);

            if (! $user || $user->isGuest()) {
                continue;
            }

            // Get TabloUserProgress
            $progress = TabloUserProgress::where('user_id', $user->id)
                ->where(function ($query) use ($workSession) {
                    $query->where('work_session_id', $workSession->id)
                        ->orWhereHas('childWorkSession', function ($q) use ($workSession) {
                            $q->where('parent_work_session_id', $workSession->id);
                        });
                })
                ->first();

            if (! $progress) {
                Log::warning('No tablo progress found for user', [
                    'user_id' => $user->id,
                    'work_session_id' => $workSession->id,
                ]);
                continue;
            }

            $stepsData = $progress->steps_data ?? [];

            // Get photo IDs based on type
            $photoIds = match ($photoType) {
                'claimed' => $stepsData['claimed_photo_ids'] ?? [],
                'retus' => $stepsData['retouch_photo_ids'] ?? [],
                'tablo' => isset($stepsData['tablo_photo_id']) ? [$stepsData['tablo_photo_id']] : [],
                default => [],
            };

            // Skip if no photos
            if (empty($photoIds)) {
                Log::info('Skipping user with no photos of selected type', [
                    'user_id' => $user->id,
                    'photo_type' => $photoType,
                ]);
                continue;
            }

            // Determine folder path based on school class grouping
            $userFolderName = $this->sanitizeFolderName($user->name);

            if ($useSchoolClassGrouping) {
                // Get user's school class from registration
                $registration = $registrations->get($userId);
                $schoolClassFolder = 'Egyéb'; // Default for users without class

                if ($registration && $registration->schoolClass) {
                    $schoolClass = $registration->schoolClass;

                    // Build folder name: "School - GradeLabel"
                    $schoolName = $schoolClass->school ?? 'Iskola';

                    // Determine class name based on data format
                    $grade = $schoolClass->grade ?? '';
                    $label = $schoolClass->label ?? '';

                    // If grade is a year (>100), use only label (e.g., grade=2026, label=12 → "12")
                    if (is_numeric($grade) && (int)$grade > 100) {
                        $className = trim($label);
                    }
                    // If label already contains grade (e.g., label="12.B"), use only label
                    elseif (!empty($grade) && !empty($label) && str_starts_with($label, (string)$grade)) {
                        $className = trim($label);
                    }
                    // Otherwise, concatenate grade + label (e.g., grade=9, label=A → "9A")
                    else {
                        $className = trim($grade . $label);
                    }

                    // If no class name, use school only; otherwise use "School - Class"
                    $schoolClassFolder = $className
                        ? $this->sanitizeFolderName("{$schoolName} - {$className}")
                        : $this->sanitizeFolderName($schoolName);
                }

                $userFolderPath = $workSessionFolder.'/'.$schoolClassFolder.'/'.$userFolderName;
            } else {
                // Flat structure: no school class grouping
                $userFolderPath = $workSessionFolder.'/'.$userFolderName;
            }

            // Add photos with chosen filename mode
            // Note: We don't create the folder upfront - ZipArchive creates it automatically
            // when we add the first file. This prevents empty folders.
            $usedFilenames = [];
            $addedPhotos = 0;

            foreach ($photoIds as $photoId) {
                $result = $this->addPhotoWithMode(
                    $zip,
                    $photoId,
                    $userFolderPath,
                    $user,
                    $filenameMode,
                    $usedFilenames,
                    $tempFiles
                );

                // Count successfully added photos
                if ($result === true) {
                    $addedPhotos++;
                }
            }

            // Only count as processed if at least one photo was added
            if ($addedPhotos > 0) {
                $processedUsers++;

                // Update progress after each user
                if ($downloadId) {
                    Cache::put("download_progress:{$downloadId}", [
                        'current' => $processedUsers,
                        'total' => count($userIds),
                        'status' => 'processing',
                    ], now()->addMinutes(10));
                }
            }
        }

        $zip->close();

        // Cleanup temp files
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        if ($processedUsers === 0) {
            @unlink($zipPath);

            // Update progress to failed status
            if ($downloadId) {
                Cache::put("download_progress:{$downloadId}", [
                    'current' => 0,
                    'total' => count($userIds),
                    'status' => 'failed',
                    'error' => 'No photos found for selected users and photo type',
                ], now()->addMinutes(10));
            }

            throw new \Exception('No photos found for selected users and photo type');
        }

        // Mark as completed and store file path
        if ($downloadId) {
            Cache::put("download_progress:{$downloadId}", [
                'current' => $processedUsers,
                'total' => count($userIds),
                'status' => 'completed',
                'file_path' => $zipPath,
            ], now()->addMinutes(10));
        }

        return $zipPath;
    }

    /**
     * Add a photo to the ZIP archive
     *
     * @param  ZipArchive  $zip
     * @param  int  $photoId
     * @param  string  $folderPath
     * @param  array  &$usedFilenames
     * @return void
     */
    protected function addPhotoToZip(ZipArchive $zip, int $photoId, string $folderPath, array &$usedFilenames): void
    {
        $photo = Photo::find($photoId);

        if (!$photo) {
            Log::warning('Photo not found', ['photo_id' => $photoId]);

            return;
        }

        $media = $photo->getFirstMedia('photo');

        if (!$media) {
            Log::warning('Photo has no media', ['photo_id' => $photoId]);

            return;
        }

        $originalPath = $media->getPath();

        if (!file_exists($originalPath)) {
            Log::warning('Photo file does not exist', [
                'photo_id' => $photoId,
                'path' => $originalPath,
            ]);

            return;
        }

        // Get original filename
        $originalFilename = $media->getCustomProperty('original_filename');

        if (!$originalFilename) {
            $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
            $originalFilename = "photo-{$photo->id}.{$extension}";
        }

        // Resolve unique filename
        $uniqueFilename = $this->resolveUniqueFilename($originalFilename, $usedFilenames);
        $usedFilenames[] = $uniqueFilename;

        // Add to ZIP
        $zipFilePath = $folderPath.'/'.$uniqueFilename;
        $zip->addFile($originalPath, $zipFilePath);
    }

    /**
     * Generate ZIP file name for work session
     */
    protected function generateZipFileName(WorkSession $workSession): string
    {
        $safeName = $this->sanitizeFolderName($workSession->name ?: 'work-session');

        return "{$workSession->id} - {$safeName}.zip";
    }

    /**
     * Sanitize folder name for ZIP
     */
    protected function sanitizeFolderName(string $name): string
    {
        // Remove or replace invalid characters
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $name);
        $name = trim($name);

        // Ensure not empty
        if (empty($name)) {
            $name = 'Album';
        }

        return $name;
    }

    /**
     * Clean up temporary ZIP file
     */
    public function cleanup(string $zipPath): void
    {
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }

    /**
     * Resolve unique filename by adding numbering if duplicate exists
     *
     * @param  string  $filename  Original filename
     * @param  array  $usedFilenames  Already used filenames in current context
     * @return string Unique filename with (1), (2), etc. if needed
     */
    protected function resolveUniqueFilename(string $filename, array $usedFilenames): string
    {
        // If no duplication, return original name
        if (! in_array($filename, $usedFilenames)) {
            return $filename;
        }

        // Split filename into basename and extension
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename']; // Name without extension
        $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';

        // Find unique counter
        $counter = 1;
        do {
            $newFilename = "{$basename} ({$counter}){$extension}";
            $counter++;
        } while (in_array($newFilename, $usedFilenames));

        return $newFilename;
    }

    /**
     * Add photo to ZIP with specified filename mode
     *
     * @param  ZipArchive  $zip
     * @param  int  $photoId
     * @param  string  $folderPath
     * @param  \App\Models\User  $user
     * @param  string  $filenameMode
     * @param  array  &$usedFilenames
     * @param  array  &$tempFiles  Reference to temp files array
     * @return bool True if photo was successfully added, false otherwise
     */
    protected function addPhotoWithMode(
        ZipArchive $zip,
        int $photoId,
        string $folderPath,
        $user,
        string $filenameMode,
        array &$usedFilenames,
        array &$tempFiles
    ): bool {
        $photo = Photo::find($photoId);

        if (! $photo) {
            Log::warning('Photo not found', ['photo_id' => $photoId]);

            return false;
        }

        $media = $photo->getFirstMedia('photo');

        if (! $media) {
            Log::warning('Photo has no media', ['photo_id' => $photoId]);

            return false;
        }

        $originalPath = $media->getPath();

        if (! file_exists($originalPath)) {
            Log::warning('Photo file does not exist', [
                'photo_id' => $photoId,
                'path' => $originalPath,
            ]);

            return false;
        }

        // Get original filename
        $originalFilename = $media->getCustomProperty('original_filename');
        if (! $originalFilename) {
            $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
            $originalFilename = "photo-{$photo->id}.{$extension}";
        }

        // Determine final filename based on mode
        $filename = match ($filenameMode) {
            'original' => $originalFilename,
            'user_name' => $this->getUserBasedFilename($user, $originalPath, $usedFilenames),
            'original_exif' => $originalFilename,
            default => $originalFilename,
        };

        // Resolve unique filename
        $filename = $this->resolveUniqueFilename($filename, $usedFilenames);
        $usedFilenames[] = $filename;

        $zipFilePath = $folderPath.'/'.$filename;

        // Handle EXIF mode
        if ($filenameMode === 'original_exif') {
            $exifService = app(ExifService::class);
            $tempFilePath = sys_get_temp_dir().'/'.uniqid().'_'.$filename;

            if ($exifService->setTitleMetadata($originalPath, $tempFilePath, $user->name)) {
                $zip->addFile($tempFilePath, $zipFilePath);
                $tempFiles[] = $tempFilePath;
            } else {
                // Fallback: use original without EXIF
                $zip->addFile($originalPath, $zipFilePath);
            }
        } else {
            $zip->addFile($originalPath, $zipFilePath);
        }

        return true;
    }

    /**
     * Generate user-based filename
     *
     * @param  \App\Models\User  $user
     * @param  string  $originalPath
     * @param  array  $usedFilenames
     * @return string
     */
    protected function getUserBasedFilename($user, string $originalPath, array $usedFilenames): string
    {
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        $sanitizedName = $this->sanitizeFolderName($user->name);

        return "{$sanitizedName}.{$extension}";
    }
}
