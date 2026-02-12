<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BatchUploadConversionRequest;
use App\Http\Requests\ImageConversionUploadRequest;
use App\Models\ConversionJob;
use App\Services\ImageConversionJobService;
use App\Services\StreamingZipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Képkonverziós végpontok.
 * Az üzleti logika az ImageConversionJobService-ben van.
 */
class ImageConversionController extends Controller
{
    public function __construct(
        protected ImageConversionJobService $jobService,
        protected StreamingZipService $streamingZipService
    ) {}

    /**
     * Egyetlen kép vagy ZIP fájl feltöltése.
     */
    public function upload(ImageConversionUploadRequest $request): JsonResponse
    {
        try {
            $result = $this->jobService->handleSingleUpload(
                file: $request->file('file'),
                folderPath: $request->input('folder_path'),
                jobId: $request->input('job_id')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a feltöltés során: ',
            ], 500);
        }
    }

    /**
     * Batch feltöltés: több kép vagy ZIP fájl egyszerre.
     */
    public function batchUpload(BatchUploadConversionRequest $request): JsonResponse
    {

        try {
            $result = $this->jobService->handleBatchUpload(
                files: $request->file('files'),
                folderPaths: $request->input('folder_paths', []),
                jobId: $request->input('job_id'),
                skipConversions: $request->boolean('skip_conversions', true)
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a feltöltés során: ',
            ], 500);
        }
    }

    /**
     * Konverzió indítása.
     */
    public function convert(ConversionJob $job): JsonResponse
    {
        try {
            $this->jobService->startConversion($job);

            return response()->json([
                'success' => true,
                'message' => 'Konverzió elindítva',
                'job_id' => $job->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a konverzió indításakor: ',
            ], 500);
        }
    }

    /**
     * Job státusz lekérdezése részletes fázis-követéssel.
     */
    public function status(ConversionJob $job): JsonResponse
    {
        return response()->json(
            $this->jobService->getJobStatus($job)
        );
    }

    /**
     * ZIP letöltés streaminggel.
     */
    public function download(Request $request, ConversionJob $job): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        try {
            $useStreaming = $request->input('streaming', true);
            $jobName = $request->input('job_name');

            return $this->jobService->downloadJob(
                job: $job,
                jobName: ! empty($jobName) ? $jobName : null,
                useStreaming: (bool) $useStreaming
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a letöltés során: ',
            ], 500);
        }
    }

    /**
     * ZIP generálás progress (streaming módhoz).
     */
    public function downloadProgress(ConversionJob $job): JsonResponse
    {
        $progress = $this->streamingZipService->getProgress($job);

        return response()->json([
            'success' => true,
            'progress' => $progress,
            'memory_info' => $this->streamingZipService->getMemoryInfo(),
        ]);
    }

    /**
     * Job aszinkron törlése.
     *
     * @return JsonResponse 202 Accepted - törlés elindítva háttérben
     */
    public function delete(ConversionJob $job): JsonResponse
    {
        try {
            $this->jobService->dispatchJobDeletion($job);

            return response()->json([
                'success' => true,
                'message' => 'Törlés elindítva',
            ], 202);
        } catch (\Exception $e) {
            $this->jobService->clearDeletionFlag($job->id);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a törlés indításakor: ',
            ], 500);
        }
    }
}
