<?php

namespace App\Jobs;

use App\Models\Album;
use App\Services\Contracts\FaceRecognitionServiceInterface;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFaceGrouping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * Create a new job instance
     */
    public function __construct(
        public int $albumId,
        public string $mode = 'all'
    ) {}

    /**
     * Execute the job
     */
    public function handle(FaceRecognitionServiceInterface $faceService): void
    {
        $album = Album::findOrFail($this->albumId);

        try {
            // Update status to processing
            $album->update(['face_grouping_status' => 'processing']);

            // Get photos to process
            $query = $album->photos();

            if ($this->mode === 'ungrouped') {
                $query->whereDoesntHave('faceGroups');
            }

            $photos = $query->get();
            $totalPhotos = $photos->count();

            // Update total photos count
            $album->update([
                'face_total_photos' => $totalPhotos,
                'face_processed_photos' => 0,
            ]);

            // Load existing groups for comparison
            $existingGroups = $album->faceGroups()->with('photos')->get();

            // Use the service to detect and group faces
            $faceService->detectAndGroupFaces($album, $photos);

            // Update progress to total photos count
            $album->update([
                'face_processed_photos' => $totalPhotos,
            ]);

            // Mark as completed
            $album->update(['face_grouping_status' => 'completed']);

            // Send success notification
            Notification::make()
                ->title('Arcfelismerés befejezve')
                ->body("Sikeresen feldolgozva {$album->face_processed_photos} kép az albumból: {$album->title}. {$album->faceGroups()->count()} csoport létrehozva.")
                ->success()
                ->sendToDatabase(\App\Models\User::where('role', 'super_admin')->get());
        } catch (Throwable $e) {
            Log::error('Face grouping processing failed', [
                'album_id' => $this->albumId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $album->update(['face_grouping_status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(Throwable $exception): void
    {
        $album = Album::find($this->albumId);

        if ($album) {
            $album->update(['face_grouping_status' => 'failed']);

            Notification::make()
                ->title('Arcfelismerés sikertelen')
                ->body("Hiba történt az arcfelismerés során: {$exception->getMessage()}")
                ->danger()
                ->sendToDatabase(\App\Models\User::where('role', 'super_admin')->get());
        }
    }
}
