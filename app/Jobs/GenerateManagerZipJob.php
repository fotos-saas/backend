<?php

namespace App\Jobs;

use App\Models\WorkSession;
use App\Services\WorkSessionZipService;
use App\Notifications\ZipReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateManagerZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout (10 minutes)
     */
    public $timeout = 600;

    /**
     * Number of times the job may be attempted
     */
    public $tries = 3;

    /**
     * Delete the job if its models no longer exist
     */
    public $deleteWhenMissingModels = true;

    /**
     * @param WorkSession $workSession
     * @param array $userIds
     * @param string $photoType (claimed|retus|tablo)
     * @param string $filenameMode (original|user_name|original_exif)
     * @param int $requestingUserId Az admin user aki a letöltést kérte
     * @param string|null $downloadId Progress tracking ID
     */
    public function __construct(
        public WorkSession $workSession,
        public array $userIds,
        public string $photoType,
        public string $filenameMode,
        public int $requestingUserId,
        public ?string $downloadId = null
    ) {
        $this->onQueue('default'); // Use default queue (monitored by worker)
    }

    /**
     * Execute the job
     */
    public function handle(WorkSessionZipService $zipService): void
    {
        try {
            Log::info('GenerateManagerZipJob started', [
                'work_session_id' => $this->workSession->id,
                'user_ids' => $this->userIds,
                'photo_type' => $this->photoType,
                'filename_mode' => $this->filenameMode,
                'download_id' => $this->downloadId,
            ]);

            // Update progress: started
            $this->updateProgress(0, 'ZIP előkészítése megkezdődött...');

            // Generate ZIP (temp file in storage/app/temp/zips/)
            $zipPath = $zipService->generateManagerZip(
                $this->workSession,
                $this->userIds,
                $this->photoType,
                $this->filenameMode,
                $this->downloadId
            );

            // Update progress: completed
            $this->updateProgress(100, 'ZIP elkészült!');

            // Move ZIP to persistent storage
            $filename = basename($zipPath);
            $storagePath = "temp/zips/{$this->workSession->id}/{$filename}";

            Storage::put($storagePath, file_get_contents($zipPath));

            // Delete original temp file
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            Log::info('ZIP file generated and stored', [
                'work_session_id' => $this->workSession->id,
                'storage_path' => $storagePath,
                'file_size' => Storage::size($storagePath),
            ]);

            // Send notification to requesting user
            $requestingUser = \App\Models\User::find($this->requestingUserId);

            if ($requestingUser) {
                $requestingUser->notify(new ZipReadyNotification(
                    $this->workSession,
                    $storagePath,
                    $filename
                ));

                Log::info('Notification sent to user', [
                    'user_id' => $requestingUser->id,
                    'email' => $requestingUser->email,
                ]);
            }

            // Clean up progress cache after 1 hour
            if ($this->downloadId) {
                Cache::put("download_progress:{$this->downloadId}", [
                    'status' => 'completed',
                    'progress' => 100,
                    'message' => 'ZIP elkészült!',
                    'storage_path' => $storagePath,
                    'filename' => $filename,
                ], now()->addHour());
            }

        } catch (\Exception $e) {
            Log::error('GenerateManagerZipJob failed', [
                'work_session_id' => $this->workSession->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update progress: failed
            $this->updateProgress(0, 'Hiba történt: ' . $e->getMessage(), 'failed');

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Update progress in cache
     */
    protected function updateProgress(int $progress, string $message, string $status = 'processing'): void
    {
        if (!$this->downloadId) {
            return;
        }

        Cache::put("download_progress:{$this->downloadId}", [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'work_session_id' => $this->workSession->id,
        ], now()->addHours(2));
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateManagerZipJob permanently failed', [
            'work_session_id' => $this->workSession->id,
            'error' => $exception->getMessage(),
        ]);

        // Update progress: permanently failed
        $this->updateProgress(0, 'A ZIP előkészítése sikertelen volt.', 'failed');

        // Optionally notify user about failure
        $requestingUser = \App\Models\User::find($this->requestingUserId);
        if ($requestingUser) {
            // You could send a failure notification here
        }
    }
}
