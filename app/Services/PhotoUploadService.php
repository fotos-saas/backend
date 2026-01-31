<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoUploadService
{
    /**
     * Upload photo and attach media with original filename preserved
     *
     * @param  UploadedFile|string  $file  Uploaded file or path
     * @param  int  $albumId  Album ID
     * @param  int|null  $assignedUserId  Optional user assignment
     * @param  string|null  $originalFilename  Override original filename
     */
    public function uploadPhoto(
        UploadedFile|string $file,
        int $albumId,
        ?int $assignedUserId = null,
        ?string $originalFilename = null
    ): Photo {
        // Determine temporary path and original name
        if ($file instanceof UploadedFile) {
            $temporaryPath = $file->getRealPath();
            $originalName = $originalFilename ?? $file->getClientOriginalName();
        } else {
            // If string, check if it's a storage path or absolute path
            if (str_starts_with($file, '/') || str_starts_with($file, 'C:')) {
                $temporaryPath = $file;
            } else {
                $temporaryPath = Storage::path($file);
            }
            $originalName = $originalFilename ?? basename($file);
        }

        // Calculate hash
        $hash = hash_file('sha256', $temporaryPath);
        
        // Check for duplicate in the same album
        $existingPhoto = Photo::where('album_id', $albumId)
            ->where('hash', $hash)
            ->first();
        
        if ($existingPhoto) {
            throw new \Exception(
                'Ez a kép már létezik ebben az albumban (ID: '.$existingPhoto->id.'). '.
                'A teljesen azonos képek nem tölthetők fel többször ugyanabba az albumba.'
            );
        }

        // Get image dimensions
        $imageInfo = @getimagesize($temporaryPath);

        // Create Photo record
        $photo = Photo::create([
            'album_id' => $albumId,
            'path' => '',
            'original_filename' => $originalName,
            'hash' => $hash,
            'width' => $imageInfo ? $imageInfo[0] : 0,
            'height' => $imageInfo ? $imageInfo[1] : 0,
            'assigned_user_id' => $assignedUserId,
        ]);

        // Generate ULID-based unique filename (Filament style)
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueFilename = strtoupper(Str::ulid()->toString()).'.'.$extension;

        // Add media with custom properties
        $media = $photo->addMedia($temporaryPath)
            ->usingFileName($uniqueFilename)
            ->withCustomProperties(['original_filename' => $originalName])
            ->toMediaCollection('photo', 'public');

        // Update path
        $photo->update(['path' => $media->getPath()]);

        // Vízjelezés automatikusan történik az ApplyWatermarkToPreview event listener-ben
        // amikor a preview conversion elkészül (queue-ban vagy szinkron módon)
        // Lásd: app/Listeners/ApplyWatermarkToPreview.php

        return $photo;
    }

    /**
     * Batch upload multiple photos
     *
     * @param  array  $files  Array of UploadedFile or paths
     * @param  int  $albumId  Album ID
     * @param  int|null  $assignedUserId  Optional user assignment
     * @param  array  $originalFilenames  Optional array of original filenames
     * @return array Array of Photo models
     */
    public function uploadMultiple(
        array $files,
        int $albumId,
        ?int $assignedUserId = null,
        array $originalFilenames = []
    ): array {
        $photos = [];

        foreach ($files as $index => $file) {
            $originalName = $originalFilenames[$index] ?? null;
            $photos[] = $this->uploadPhoto($file, $albumId, $assignedUserId, $originalName);
        }

        return $photos;
    }
}
