<?php

namespace App\Observers;

use App\Jobs\ProcessFaceRecognition;
use App\Models\Photo;
use App\Models\TabloUserProgress;
use Illuminate\Support\Facades\Log;

/**
 * Photo model observer.
 * Automatically triggers face recognition when photos are created.
 * Handles cascade cleanup when claimed photos are deleted.
 */
class PhotoObserver
{
    /**
     * Handle the Photo "created" event.
     * Automatically queue face recognition for newly created photos.
     */
    public function created(Photo $photo): void
    {
        // Skip if photo doesn't have a valid path
        if (! $photo->path) {
            return;
        }

        // Queue face recognition job
        ProcessFaceRecognition::dispatch($photo)->onQueue('face-recognition');

        Log::debug('Face recognition queued for photo', [
            'photo_id' => $photo->id,
            'album_id' => $photo->album_id,
        ]);
    }

    /**
     * Handle the Photo "updated" event.
     * Re-process face recognition if photo file changed.
     */
    public function updated(Photo $photo): void
    {
        // Only reprocess if path changed (new file uploaded)
        if ($photo->wasChanged('path') && $photo->path) {
            ProcessFaceRecognition::dispatch($photo)->onQueue('face-recognition');

            Log::debug('Face recognition re-queued for updated photo', [
                'photo_id' => $photo->id,
            ]);
        }
    }

    /**
     * Handle the Photo "deleting" event.
     * Remove photo ID from TabloUserProgress records (cascade cleanup).
     *
     * When a claimed photo is deleted:
     * 1. Remove from retouch_photo_ids array
     * 2. Set tablo_photo_id to null if it was this photo
     */
    public function deleting(Photo $photo): void
    {
        $photoId = $photo->id;

        // Find all progress records that reference this photo
        $affectedProgresses = TabloUserProgress::where(function ($query) use ($photoId) {
            $query->whereJsonContains('retouch_photo_ids', $photoId)
                ->orWhere('tablo_photo_id', $photoId);
        })->get();

        if ($affectedProgresses->isEmpty()) {
            return;
        }

        foreach ($affectedProgresses as $progress) {
            $changes = [];

            // Remove from retouch_photo_ids
            $retouchIds = $progress->retouch_photo_ids ?? [];
            if (in_array($photoId, $retouchIds)) {
                $newRetouchIds = array_values(array_filter($retouchIds, fn($id) => $id !== $photoId));
                $progress->retouch_photo_ids = $newRetouchIds;
                $changes['retouch_photo_ids'] = [
                    'removed' => $photoId,
                    'remaining_count' => count($newRetouchIds),
                ];
            }

            // Set tablo_photo_id to null if it was this photo
            if ($progress->tablo_photo_id === $photoId) {
                $progress->tablo_photo_id = null;
                $changes['tablo_photo_id'] = [
                    'removed' => $photoId,
                    'set_to' => null,
                ];
            }

            if (! empty($changes)) {
                $progress->save();

                Log::info('[PhotoCascadeDelete] Removed photo from TabloUserProgress', [
                    'photo_id' => $photoId,
                    'progress_id' => $progress->id,
                    'user_id' => $progress->user_id,
                    'changes' => $changes,
                ]);
            }
        }
    }
}
