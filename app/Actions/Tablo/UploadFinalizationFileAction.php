<?php

namespace App\Actions\Tablo;

use App\Exceptions\FileValidationException;
use App\Models\TabloProject;
use App\Services\FileStorageService;
use App\Services\Tablo\FinalizationSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFinalizationFileAction
{
    public function __construct(
        private FinalizationSecurityService $security,
        private FileStorageService $fileStorage,
    ) {}

    /**
     * Upload and store a finalization file (background image or attachment).
     *
     * @return array{success: bool, message: string, fileId?: string, filename?: string, url?: string, status?: int}
     */
    public function execute(TabloProject $tabloProject, UploadedFile $file, string $type, string $ip): array
    {
        $projectId = $tabloProject->id;

        try {
            if ($type === 'background') {
                $this->fileStorage->validateImage($file, $this->security->getAllowedImageExtensions(), 16 * 1024 * 1024, true);
            } else {
                $this->fileStorage->validateArchive($file, $this->security->getAllowedArchiveExtensions());
            }
        } catch (FileValidationException $e) {
            if (str_contains($e->getMessage(), 'tartalma nem egyezik')) {
                $eventType = $type === 'background' ? 'invalid_image_magic_bytes' : 'invalid_archive_magic_bytes';
                $this->security->logSecurityEvent($eventType, $projectId, [
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'ip' => $ip,
                ]);
            }

            return [
                'success' => false,
                'message' => $this->getValidationMessage($type, $e),
                'status' => 422,
            ];
        }

        $directory = "tablo-projects/{$projectId}/{$type}";
        $result = $this->fileStorage->storeWithSafeName($file, $directory);

        $this->updateProjectData($tabloProject, $type, $result->path, $result->originalName);

        $this->security->logSecurityEvent('file_uploaded', $projectId, [
            'type' => $type,
            'filename' => $result->filename,
            'ip' => $ip,
        ]);

        return [
            'success' => true,
            'message' => 'Fájl sikeresen feltöltve!',
            'fileId' => $result->path,
            'filename' => $result->originalName,
            'url' => $this->fileStorage->url($result->path),
        ];
    }

    private function getValidationMessage(string $type, FileValidationException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'kiterjesztés')) {
            return $type === 'background'
                ? 'Csak JPG, JPEG vagy BMP fájl tölthető fel háttérképként!'
                : 'Csak ZIP, RAR vagy 7Z fájl tölthető fel csatolmányként!';
        }

        if (str_contains($message, 'túl nagy')) {
            return 'A háttérkép maximum 16MB lehet!';
        }

        return $message;
    }

    private function updateProjectData(TabloProject $tabloProject, string $type, string $path, string $originalName): void
    {
        $existingData = $tabloProject->data ?? [];

        if ($type === 'background') {
            if (! empty($existingData['background'])) {
                Storage::disk('public')->delete($existingData['background']);
            }
            $existingData['background'] = $path;
        } else {
            $otherFiles = $existingData['other_files'] ?? [];
            $otherFiles[] = [
                'path' => $path,
                'filename' => $originalName,
                'uploaded_at' => now()->toIso8601String(),
            ];
            $existingData['other_files'] = $otherFiles;
        }

        $tabloProject->data = $existingData;
        $tabloProject->save();
    }
}
