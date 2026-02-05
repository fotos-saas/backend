<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use App\Services\Tablo\FinalizationSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFinalizationFileAction
{
    public function __construct(
        private FinalizationSecurityService $security,
    ) {}

    /**
     * Upload and store a finalization file (background image or attachment).
     *
     * @return array{success: bool, message: string, fileId?: string, filename?: string, url?: string, status?: int}
     */
    public function execute(TabloProject $tabloProject, UploadedFile $file, string $type, string $ip): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $projectId = $tabloProject->id;

        $validationResult = $this->validateFile($file, $type, $extension, $projectId, $ip);
        if ($validationResult !== null) {
            return $validationResult;
        }

        $originalName = $file->getClientOriginalName();
        $filename = $this->security->generateSafeFilename($originalName, $extension);

        $path = $file->storeAs(
            "tablo-projects/{$projectId}/{$type}",
            $filename,
            'public'
        );

        $this->updateProjectData($tabloProject, $type, $path, $originalName);

        $this->security->logSecurityEvent('file_uploaded', $projectId, [
            'type' => $type,
            'filename' => $filename,
            'ip' => $ip,
        ]);

        return [
            'success' => true,
            'message' => 'Fájl sikeresen feltöltve!',
            'fileId' => $path,
            'filename' => $originalName,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    private function validateFile(UploadedFile $file, string $type, string $extension, int $projectId, string $ip): ?array
    {
        if ($type === 'background') {
            return $this->validateBackgroundFile($file, $extension, $projectId, $ip);
        }

        return $this->validateAttachmentFile($file, $extension, $projectId, $ip);
    }

    private function validateBackgroundFile(UploadedFile $file, string $extension, int $projectId, string $ip): ?array
    {
        if (! in_array($extension, $this->security->getAllowedImageExtensions(), true)) {
            return [
                'success' => false,
                'message' => 'Csak JPG, JPEG vagy BMP fájl tölthető fel háttérképként!',
                'status' => 422,
            ];
        }

        if (! $this->security->validateImageMagicBytes($file)) {
            $this->security->logSecurityEvent('invalid_image_magic_bytes', $projectId, [
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'ip' => $ip,
            ]);

            return [
                'success' => false,
                'message' => 'A fájl tartalma nem egyezik a kiterjesztéssel!',
                'status' => 422,
            ];
        }

        if ($file->getSize() > 16 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => 'A háttérkép maximum 16MB lehet!',
                'status' => 422,
            ];
        }

        return null;
    }

    private function validateAttachmentFile(UploadedFile $file, string $extension, int $projectId, string $ip): ?array
    {
        if (! in_array($extension, $this->security->getAllowedArchiveExtensions(), true)) {
            return [
                'success' => false,
                'message' => 'Csak ZIP, RAR vagy 7Z fájl tölthető fel csatolmányként!',
                'status' => 422,
            ];
        }

        if (! $this->security->validateArchiveMagicBytes($file)) {
            $this->security->logSecurityEvent('invalid_archive_magic_bytes', $projectId, [
                'filename' => $file->getClientOriginalName(),
                'ip' => $ip,
            ]);

            return [
                'success' => false,
                'message' => 'A fájl tartalma nem egyezik a kiterjesztéssel!',
                'status' => 422,
            ];
        }

        return null;
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
