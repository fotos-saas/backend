<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImageConversionUploadRequest;
use App\Jobs\ConvertImageBatchJob;
use App\Models\ConversionJob;
use App\Services\ImageConversionService;
use App\Services\StreamingZipService;
use App\Services\ZipExtractService;
use App\Services\ZipGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ImageConversionController extends Controller
{
    public function __construct(
        protected ImageConversionService $conversionService,
        protected ZipGeneratorService $zipService,
        protected StreamingZipService $streamingZipService,
        protected ZipExtractService $zipExtractService
    ) {}

    /**
     * Upload single image or ZIP file
     * OPTIMIZED: No blocking wait - thumbnail generation happens in background queue
     * ZIP files are extracted and images are processed individually
     */
    public function upload(ImageConversionUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $folderPath = $request->input('folder_path');
            $jobId = $request->input('job_id');

            // Get or create job
            if ($jobId) {
                $job = ConversionJob::findOrFail($jobId);
            } else {
                $job = $this->conversionService->createJob();
            }

            // Check if ZIP file
            if ($this->zipExtractService->isZipFile($file)) {
                // Extract ZIP and store all images
                $uploadedMedia = $this->zipExtractService->extractAndStore($file, $job, true);

                // Update job progress
                $this->conversionService->updateJobProgress($job);

                // Dispatch thumbnail generation job to queue (non-blocking)
                if (count($uploadedMedia) > 0) {
                    \App\Jobs\GenerateThumbnailsJob::dispatch($job);
                }

                return response()->json([
                    'success' => true,
                    'job_id' => $job->id,
                    'uploaded_count' => count($uploadedMedia),
                    'media' => $uploadedMedia,
                    'message' => 'ZIP sikeresen kicsomagolva, ' . count($uploadedMedia) . ' kép feltöltve',
                ]);
            }

            // Regular image upload
            $media = $this->conversionService->storeImage($job, $file, $folderPath, true);

            // Update job progress
            $this->conversionService->updateJobProgress($job);

            // Dispatch thumbnail generation job to queue (non-blocking)
            \App\Jobs\GenerateThumbnailsJob::dispatch($job);

            return response()->json([
                'success' => true,
                'job_id' => $job->id,
                'media_id' => $media->id,
                'thumb_url' => null, // Will be available after queue processing
                'preview_url' => null, // Will be available after queue processing
                'original_name' => $media->getOriginalFilename(),
                'folder_path' => $media->folder_path,
                'phase' => 'uploaded', // Frontend should poll for thumbnail status
                'message' => 'Kép sikeresen feltöltve, thumbnail generálás folyamatban',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a feltöltés során: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch upload multiple images or ZIP files WITHOUT waiting for conversions
     * FAST: Only stores files, conversions happen in background job
     * ZIP files are extracted and images are processed individually
     */
    public function batchUpload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            // Use 'extensions' for modern format compatibility (Apple, Modern, RAW, Traditional, ZIP)
            'files.*' => 'file|max:204800|extensions:heic,heif,webp,avif,jxl,dng,cr2,nef,arw,orf,rw2,jpeg,jpg,png,bmp,zip',
            'job_id' => 'nullable|exists:conversion_jobs,id',
            'folder_paths' => 'nullable|array',
            'skip_conversions' => 'nullable|boolean', // NEW: Flag to skip conversions
        ]);

        try {
            $jobId = $request->input('job_id');
            $folderPaths = $request->input('folder_paths', []);
            $skipConversions = $request->boolean('skip_conversions', true); // Default: TRUE (skip)

            // Get or create job
            if ($jobId) {
                $job = ConversionJob::findOrFail($jobId);
            } else {
                $job = $this->conversionService->createJob();
            }

            $uploadedMedia = [];

            foreach ($request->file('files') as $index => $file) {
                $folderPath = $folderPaths[$index] ?? null;

                // Skip hidden files
                if ($this->conversionService->isHiddenFile($file->getClientOriginalName())) {
                    continue;
                }

                // Check if ZIP file
                if ($this->zipExtractService->isZipFile($file)) {
                    // Extract ZIP and store all images
                    $zipMedia = $this->zipExtractService->extractAndStore($file, $job, $skipConversions);
                    $uploadedMedia = array_merge($uploadedMedia, $zipMedia);
                    continue;
                }

                // Regular image - FAST UPLOAD: Skip conversions (no thumbnail generation)
                $media = $this->conversionService->storeImage(
                    $job,
                    $file,
                    $folderPath,
                    $skipConversions
                );

                // NO WAITING! Return immediately
                $uploadedMedia[] = [
                    'id' => $media->id,
                    'original_name' => $media->getOriginalFilename(),
                    'folder_path' => $media->folder_path,
                    'phase' => $media->getPhase(), // 'uploaded'
                    // thumb_url and preview_url will be null initially
                    'thumb_url' => null,
                    'preview_url' => null,
                ];
            }

            // Update job progress
            $this->conversionService->updateJobProgress($job);

            // DISPATCH GenerateThumbnailsJob to queue
            if ($skipConversions && count($uploadedMedia) > 0) {
                \App\Jobs\GenerateThumbnailsJob::dispatch($job);
            }

            return response()->json([
                'success' => true,
                'job_id' => $job->id,
                'uploaded_count' => count($uploadedMedia),
                'media' => $uploadedMedia,
                'message' => 'Képek sikeresen feltöltve, thumbnail generálás folyamatban',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a feltöltés során: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start conversion process
     */
    public function convert(ConversionJob $job): JsonResponse
    {
        try {
            // Update job status to converting
            $job->update(['status' => 'converting']);

            // Dispatch conversion job to queue
            ConvertImageBatchJob::dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Konverzió elindítva',
                'job_id' => $job->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a konverzió indításakor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get conversion job status with detailed phase tracking
     */
    public function status(ConversionJob $job): JsonResponse
    {
        // Refresh job data and eager load all media with their Spatie media
        $job->refresh();
        $job->load(['media.media']);

        // Map all media with thumbnail URLs and phase
        // AUTO-UPDATE conversion_status if conversions are ready
        $mediaWithCounts = $job->media->map(function ($media) {
            // Check if conversions are physically complete
            if ($media->conversion_status === 'converting') {
                $thumbUrl = $media->getThumbUrl();
                $previewUrl = $media->getPreviewUrl();

                // If both thumb and preview exist → mark as completed
                if ($thumbUrl && $previewUrl) {
                    $media->update([
                        'conversion_status' => 'completed',
                        'conversion_completed_at' => now(),
                    ]);
                }
            }

            return [
                'id' => $media->id,
                'original_name' => $media->getOriginalFilename(),
                'folder_path' => $media->folder_path,
                'thumb_url' => $media->getThumbUrl(),
                'preview_url' => $media->getPreviewUrl(),
                'conversion_status' => $media->conversion_status,
                'phase' => $media->getPhase(),
                'is_uploaded' => $media->isUploadCompleted(), // For counting only
            ];
        });

        // Calculate counts from mapped data (reflects auto-updates)
        $totalMedia = $mediaWithCounts->count();
        $uploadedMedia = $mediaWithCounts->filter(fn ($m) => $m['is_uploaded'])->count();
        $convertingMedia = $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'converting')->count();
        $completedMedia = $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'completed')->count();
        $failedMedia = $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'failed')->count();

        // Remove is_uploaded from response (only used for counting)
        $allMedia = $mediaWithCounts->map(function ($m) {
            unset($m['is_uploaded']);
            return $m;
        })->values();

        // Overall progress percentage
        $uploadProgress = $totalMedia > 0 ? round(($uploadedMedia / $totalMedia) * 100) : 0;
        $conversionProgress = $totalMedia > 0 ? round(($completedMedia / $totalMedia) * 100) : 0;

        // Determine current phase
        $phase = 'pending';
        if ($failedMedia > 0 && $completedMedia === 0) {
            $phase = 'failed';
        } elseif ($completedMedia === $totalMedia && $totalMedia > 0) {
            $phase = 'ready';
        } elseif ($convertingMedia > 0 || $completedMedia > 0) {
            $phase = 'converting';
        } elseif ($uploadedMedia === $totalMedia && $totalMedia > 0) {
            $phase = 'uploaded';
        } elseif ($uploadedMedia > 0) {
            $phase = 'uploading';
        }

        // Map failed images
        $failedMediaList = $job->media
            ->filter(fn ($media) => $media->conversion_status === 'failed')
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'original_name' => $media->getOriginalFilename(),
                ];
            })
            ->values();

        // CRITICAL: Calculate is_completed dynamically based on ACTUAL completion state
        // Don't trust job->status, check if ALL media have completed conversions
        $isActuallyCompleted = $totalMedia > 0 && $completedMedia === $totalMedia;

        return response()->json([
            'job_id' => $job->id,
            'job_name' => $job->job_name,
            'status' => $job->status,
            'phase' => $phase, // NEW: Global phase
            'total_files' => $totalMedia,
            'uploaded_files' => $uploadedMedia, // NEW
            'converting_files' => $convertingMedia, // NEW
            'completed_files' => $completedMedia, // NEW
            'failed_files' => $failedMedia,
            'upload_progress' => $uploadProgress, // NEW: 0-100%
            'conversion_progress' => $conversionProgress, // NEW: 0-100%
            'media' => $allMedia,
            'failed_media' => $failedMediaList,
            'is_completed' => $isActuallyCompleted, // Dynamic calculation based on actual media state
        ]);
    }

    /**
     * Download ZIP file with streaming
     */
    public function download(Request $request, ConversionJob $job): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        try {
            // Update job name if provided
            if ($request->has('job_name') && ! empty($request->input('job_name'))) {
                $job->update(['job_name' => $request->input('job_name')]);
            }

            // Check if streaming is available and enabled
            $useStreaming = $request->input('streaming', true) &&
                           $this->streamingZipService->isStreamingAvailable();

            if ($useStreaming) {
                // Use streaming for memory efficiency
                return $this->streamingZipService->streamZip($job);
            } else {
                // Fallback to traditional method
                $zipPath = $this->zipService->generateZip($job);
                return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a letöltés során: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ZIP generation progress (for streaming)
     */
    public function downloadProgress(ConversionJob $job): JsonResponse
    {
        $progress = $this->streamingZipService->getProgress($job);

        return response()->json([
            'success' => true,
            'progress' => $progress,
            'memory_info' => $this->streamingZipService->getMemoryInfo()
        ]);
    }

    /**
     * Delete conversion job and all media (ASYNC)
     *
     * Dispatches DeleteConversionJobAsync to queue and returns immediately.
     * This provides instant UI response (~200ms) regardless of file count.
     *
     * @return JsonResponse 202 Accepted - deletion started in background
     */
    public function delete(ConversionJob $job): JsonResponse
    {
        try {
            $jobId = $job->id;

            // Mark job as being deleted (prevents other jobs from processing)
            Cache::put("job_deleting_{$jobId}", true, now()->addMinutes(10));

            // Dispatch async deletion job to queue
            \App\Jobs\DeleteConversionJobAsync::dispatch($jobId);

            // Return immediately with 202 Accepted
            return response()->json([
                'success' => true,
                'message' => 'Törlés elindítva',
            ], 202);
        } catch (\Exception $e) {
            // Clear the flag if dispatch failed
            Cache::forget("job_deleting_{$job->id}");

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a törlés indításakor: '.$e->getMessage(),
            ], 500);
        }
    }
}
