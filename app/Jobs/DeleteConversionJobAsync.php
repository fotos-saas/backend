<?php

namespace App\Jobs;

use App\Models\ConversionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Async job deletion - deletes conversion jobs and all associated media files
 * in the background. This provides instant UI response while heavy file
 * deletion happens in the queue.
 *
 * Performance: 100 images ~200ms API response (vs 3-5s sync)
 *              1000 images ~200ms API response (vs 35s+ sync)
 */
class DeleteConversionJobAsync implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     * 10 minutes should be enough even for 1000+ images.
     */
    public int $timeout = 600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $jobId
    ) {}

    /**
     * Execute the job: Delete all media files and the job itself.
     */
    public function handle(): void
    {
        $job = ConversionJob::with('media.media')->find($this->jobId);

        if (!$job) {
            Log::warning("DeleteConversionJobAsync: Job {$this->jobId} not found (may already be deleted)");
            Cache::forget("job_deleting_{$this->jobId}");
            return;
        }

        $mediaCount = $job->media->count();
        Log::info("DeleteConversionJobAsync: Starting deletion of job {$this->jobId} with {$mediaCount} media files");

        $deletedCount = 0;
        $failedCount = 0;

        // Delete all media files
        foreach ($job->media as $media) {
            try {
                // Clear Spatie media collection (deletes physical files)
                $media->clearMediaCollection('image_conversion');
                // Delete the ConversionMedia record
                $media->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                Log::error("DeleteConversionJobAsync: Failed to delete media {$media->id}: {$e->getMessage()}");
                $failedCount++;
            }

            // Run garbage collection every 20 items to free memory
            if ($deletedCount % 20 === 0) {
                gc_collect_cycles();
            }
        }

        // Delete the job record
        try {
            $job->delete();
            Log::info("DeleteConversionJobAsync: Job {$this->jobId} deleted successfully. Deleted: {$deletedCount}, Failed: {$failedCount}");
        } catch (\Exception $e) {
            Log::error("DeleteConversionJobAsync: Failed to delete job record {$this->jobId}: {$e->getMessage()}");
        }

        // Clear cache flags
        Cache::forget("job_deleting_{$this->jobId}");
        Cache::forget("conversion_job_{$this->jobId}_progress");
        Cache::forget("conversion_job_{$this->jobId}_thumbnail_progress");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("DeleteConversionJobAsync: Job {$this->jobId} deletion failed after {$this->tries} attempts. Error: {$exception->getMessage()}");

        // Clear the deleting flag so the job can be retried manually
        Cache::forget("job_deleting_{$this->jobId}");
    }
}
