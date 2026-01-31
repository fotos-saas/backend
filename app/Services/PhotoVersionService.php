<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PhotoVersionService
{
    /**
     * Create a version of the current photo before replacement
     */
    public function createVersion(Photo $photo, ?string $reason, User $replacedBy): PhotoVersion
    {
        $currentMedia = $photo->getFirstMedia('photo');

        if (! $currentMedia) {
            throw new \Exception('No media found for this photo');
        }

        // Create version record
        $version = PhotoVersion::create([
            'photo_id' => $photo->id,
            'media_id' => $currentMedia->id,
            'path' => $photo->path,
            'original_filename' => $currentMedia->getCustomProperty('original_filename') ?? $currentMedia->file_name,
            'reason' => $reason,
            'replaced_by_user_id' => $replacedBy->id,
            'is_restored' => false,
            'width' => $photo->width,
            'height' => $photo->height,
        ]);

        // Copy media file to a version-specific collection
        $versionCollectionName = 'photo_version_'.$version->id;

        try {
            // Get the full path of the current media file
            $sourcePath = $currentMedia->getPath();

            // Copy the current media to version collection
            if (file_exists($sourcePath)) {
                $photo->addMedia($sourcePath)
                    ->preservingOriginal()
                    ->usingName($currentMedia->name)
                    ->usingFileName('version_'.$version->id.'_'.$currentMedia->file_name)
                    ->withCustomProperties(['original_filename' => $currentMedia->getCustomProperty('original_filename') ?? $currentMedia->file_name])
                    ->toMediaCollection($versionCollectionName, 'public');

                \Log::info("Photo version created successfully: version_id={$version->id}, collection={$versionCollectionName}");
            } else {
                \Log::warning("Source media file not found: {$sourcePath}");
            }
        } catch (\Exception $e) {
            // Log error but don't fail the version creation
            \Log::error('Failed to copy media for version: '.$e->getMessage(), [
                'version_id' => $version->id,
                'photo_id' => $photo->id,
                'media_id' => $currentMedia->id,
            ]);
        }

        return $version;
    }

    /**
     * Restore a previous version (creates a new version from current, then swaps)
     */
    public function restoreVersion(Photo $photo, PhotoVersion $version, User $restoredBy): void
    {
        // First, create a version from the current photo
        $this->createVersion($photo, 'Visszaállítás előtti verzió', $restoredBy);

        // Get the version's media
        $versionCollectionName = 'photo_version_'.$version->id;
        $versionMedia = $photo->getFirstMedia($versionCollectionName);

        if (! $versionMedia) {
            // Fallback to media_id if collection doesn't exist
            $versionMedia = $version->media;
        }

        if (! $versionMedia) {
            throw new \Exception('Version media not found');
        }

        // Clear current photo media
        $photo->clearMediaCollection('photo');

        // Copy version media back to photo collection
        $restoredMedia = $photo->addMedia($versionMedia->getPath())
            ->preservingOriginal()
            ->usingName($versionMedia->name)
            ->usingFileName($versionMedia->file_name)
            ->toMediaCollection('photo', 'public');

        // Update photo metadata
        $photo->update([
            'path' => $version->path,
            'width' => $version->width,
            'height' => $version->height,
        ]);

        // Vízjelezés automatikusan történik az ApplyWatermarkToPreview event listener-ben
        // amikor a preview conversion elkészül (queue-ban vagy szinkron módon)
        // Lásd: app/Listeners/ApplyWatermarkToPreview.php

        // Mark this version as restored
        $version->update(['is_restored' => true]);
    }

    /**
     * Get latest versions for a photo
     */
    public function getLatestVersions(Photo $photo, int $limit = 20): Collection
    {
        return $photo->versions()->limit($limit)->get();
    }
}
