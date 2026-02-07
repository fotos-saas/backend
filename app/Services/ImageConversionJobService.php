<?php

namespace App\Services;

use App\Jobs\ConvertImageBatchJob;
use App\Jobs\DeleteConversionJobAsync;
use App\Jobs\GenerateThumbnailsJob;
use App\Models\ConversionJob;
use App\Models\ConversionMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * Konverziós job orchestráció: upload flow, status, letöltés, törlés.
 * A low-level képkezelés az ImageConversionService-ben marad.
 */
class ImageConversionJobService
{
    public function __construct(
        protected ImageConversionService $conversionService,
        protected ZipGeneratorService $zipService,
        protected StreamingZipService $streamingZipService,
        protected ZipExtractService $zipExtractService
    ) {}

    /**
     * Egyetlen kép vagy ZIP feltöltés kezelése.
     */
    public function handleSingleUpload(
        UploadedFile $file,
        ?string $folderPath,
        ?int $jobId
    ): array {
        $job = $jobId
            ? ConversionJob::findOrFail($jobId)
            : $this->conversionService->createJob();

        // ZIP fájl kezelése
        if ($this->zipExtractService->isZipFile($file)) {
            $uploadedMedia = $this->zipExtractService->extractAndStore($file, $job, true);
            $this->conversionService->updateJobProgress($job);

            if (count($uploadedMedia) > 0) {
                GenerateThumbnailsJob::dispatch($job);
            }

            return [
                'success' => true,
                'job_id' => $job->id,
                'uploaded_count' => count($uploadedMedia),
                'media' => $uploadedMedia,
                'message' => 'ZIP sikeresen kicsomagolva, ' . count($uploadedMedia) . ' kép feltöltve',
            ];
        }

        // Egyedi kép feltöltés
        $media = $this->conversionService->storeImage($job, $file, $folderPath, true);
        $this->conversionService->updateJobProgress($job);
        GenerateThumbnailsJob::dispatch($job);

        return [
            'success' => true,
            'job_id' => $job->id,
            'media_id' => $media->id,
            'thumb_url' => null,
            'preview_url' => null,
            'original_name' => $media->getOriginalFilename(),
            'folder_path' => $media->folder_path,
            'phase' => 'uploaded',
            'message' => 'Kép sikeresen feltöltve, thumbnail generálás folyamatban',
        ];
    }

    /**
     * Batch feltöltés kezelése (több kép vagy ZIP fájl).
     */
    public function handleBatchUpload(
        array $files,
        array $folderPaths,
        ?int $jobId,
        bool $skipConversions = true
    ): array {
        $job = $jobId
            ? ConversionJob::findOrFail($jobId)
            : $this->conversionService->createJob();

        $uploadedMedia = [];

        foreach ($files as $index => $file) {
            $folderPath = $folderPaths[$index] ?? null;

            // Rejtett fájlok kihagyása
            if ($this->conversionService->isHiddenFile($file->getClientOriginalName())) {
                continue;
            }

            // ZIP fájl kezelése
            if ($this->zipExtractService->isZipFile($file)) {
                $zipMedia = $this->zipExtractService->extractAndStore($file, $job, $skipConversions);
                $uploadedMedia = array_merge($uploadedMedia, $zipMedia);

                continue;
            }

            // Egyedi kép - gyors feltöltés, konverzió kihagyása
            $media = $this->conversionService->storeImage(
                $job,
                $file,
                $folderPath,
                $skipConversions
            );

            $uploadedMedia[] = [
                'id' => $media->id,
                'original_name' => $media->getOriginalFilename(),
                'folder_path' => $media->folder_path,
                'phase' => $media->getPhase(),
                'thumb_url' => null,
                'preview_url' => null,
            ];
        }

        $this->conversionService->updateJobProgress($job);

        // Thumbnail generálás indítása queue-ban
        if ($skipConversions && count($uploadedMedia) > 0) {
            GenerateThumbnailsJob::dispatch($job);
        }

        return [
            'success' => true,
            'job_id' => $job->id,
            'uploaded_count' => count($uploadedMedia),
            'media' => $uploadedMedia,
            'message' => 'Képek sikeresen feltöltve, thumbnail generálás folyamatban',
        ];
    }

    /** Konverzió indítása queue-ban. */
    public function startConversion(ConversionJob $job): void
    {
        $job->update(['status' => 'converting']);
        ConvertImageBatchJob::dispatch($job);
    }

    /** Job státusz összeállítása részletes fázis-követéssel. */
    public function getJobStatus(ConversionJob $job): array
    {
        $job->refresh();
        $job->load(['media.media']);

        // Media mapping + automatikus állapot-frissítés
        $mediaWithCounts = $job->media->map(function (ConversionMedia $media) {
            $this->autoCompleteMediaIfReady($media);

            return [
                'id' => $media->id,
                'original_name' => $media->getOriginalFilename(),
                'folder_path' => $media->folder_path,
                'thumb_url' => $media->getThumbUrl(),
                'preview_url' => $media->getPreviewUrl(),
                'conversion_status' => $media->conversion_status,
                'phase' => $media->getPhase(),
                'is_uploaded' => $media->isUploadCompleted(),
            ];
        });

        $counts = $this->calculateMediaCounts($mediaWithCounts);

        // is_uploaded eltávolítása a válaszból (csak számoláshoz kellett)
        $allMedia = $mediaWithCounts->map(function ($m) {
            unset($m['is_uploaded']);
            return $m;
        })->values();

        // Progress százalékok
        $uploadProgress = $counts['total'] > 0
            ? round(($counts['uploaded'] / $counts['total']) * 100) : 0;
        $conversionProgress = $counts['total'] > 0
            ? round(($counts['completed'] / $counts['total']) * 100) : 0;

        $phase = $this->determineJobPhase($counts);

        // Sikertelen média lista
        $failedMediaList = $job->media
            ->filter(fn ($media) => $media->conversion_status === 'failed')
            ->map(fn ($media) => [
                'id' => $media->id,
                'original_name' => $media->getOriginalFilename(),
            ])
            ->values();

        $isActuallyCompleted = $counts['total'] > 0 && $counts['completed'] === $counts['total'];

        return [
            'job_id' => $job->id,
            'job_name' => $job->job_name,
            'status' => $job->status,
            'phase' => $phase,
            'total_files' => $counts['total'],
            'uploaded_files' => $counts['uploaded'],
            'converting_files' => $counts['converting'],
            'completed_files' => $counts['completed'],
            'failed_files' => $counts['failed'],
            'upload_progress' => $uploadProgress,
            'conversion_progress' => $conversionProgress,
            'media' => $allMedia,
            'failed_media' => $failedMediaList,
            'is_completed' => $isActuallyCompleted,
        ];
    }

    /** ZIP letöltés kezelése (streaming vagy hagyományos). */
    public function downloadJob(
        ConversionJob $job,
        ?string $jobName,
        bool $useStreaming = true
    ): \Symfony\Component\HttpFoundation\StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse {
        // Job név frissítése ha meg lett adva
        if ($jobName) {
            $job->update(['job_name' => $jobName]);
        }

        $canStream = $useStreaming && $this->streamingZipService->isStreamingAvailable();

        if ($canStream) {
            return $this->streamingZipService->streamZip($job);
        }

        // Fallback hagyományos módszer
        $zipPath = $this->zipService->generateZip($job);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    /** Job aszinkron törlés indítása. */
    public function dispatchJobDeletion(ConversionJob $job): void
    {
        Cache::put("job_deleting_{$job->id}", true, now()->addMinutes(10));
        DeleteConversionJobAsync::dispatch($job->id);
    }

    /** Törlési cache flag eltávolítása (dispatch hiba esetén). */
    public function clearDeletionFlag(int $jobId): void
    {
        Cache::forget("job_deleting_{$jobId}");
    }

    /** Ha a media converting státuszban van de kész, auto-complete. */
    private function autoCompleteMediaIfReady(ConversionMedia $media): void
    {
        if ($media->conversion_status !== 'converting') {
            return;
        }

        $thumbUrl = $media->getThumbUrl();
        $previewUrl = $media->getPreviewUrl();

        if ($thumbUrl && $previewUrl) {
            $media->update([
                'conversion_status' => 'completed',
                'conversion_completed_at' => now(),
            ]);
        }
    }

    /** Media statisztikák számítása a mappelt collection-ből. */
    private function calculateMediaCounts($mediaWithCounts): array
    {
        return [
            'total' => $mediaWithCounts->count(),
            'uploaded' => $mediaWithCounts->filter(fn ($m) => $m['is_uploaded'])->count(),
            'converting' => $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'converting')->count(),
            'completed' => $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'completed')->count(),
            'failed' => $mediaWithCounts->filter(fn ($m) => $m['conversion_status'] === 'failed')->count(),
        ];
    }

    /** Globális job fázis meghatározása a media számok alapján. */
    private function determineJobPhase(array $counts): string
    {
        if ($counts['failed'] > 0 && $counts['completed'] === 0) {
            return 'failed';
        }
        if ($counts['completed'] === $counts['total'] && $counts['total'] > 0) {
            return 'ready';
        }
        if ($counts['converting'] > 0 || $counts['completed'] > 0) {
            return 'converting';
        }
        if ($counts['uploaded'] === $counts['total'] && $counts['total'] > 0) {
            return 'uploaded';
        }
        if ($counts['uploaded'] > 0) {
            return 'uploading';
        }

        return 'pending';
    }
}
