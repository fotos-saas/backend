<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Gallery\DeleteGalleryPhotosRequest;
use App\Http\Requests\Gallery\UploadGalleryPhotosRequest;
use App\Models\TabloGallery;
use App\Models\TabloProject;
use App\Models\TabloUserProgress;
use App\Services\MediaZipService;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Partner Gallery Controller
 *
 * Manages gallery CRUD and photo uploads for partner projects.
 */
class PartnerGalleryController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Get gallery details for a project.
     */
    public function getGallery(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'hasGallery' => false,
                'gallery' => null,
            ]);
        }

        $gallery = $project->gallery;

        return response()->json([
            'hasGallery' => true,
            'gallery' => $this->formatGallery($gallery),
        ]);
    }

    /**
     * Create or get gallery for a project.
     */
    public function createOrGetGallery(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if ($project->tablo_gallery_id) {
            return response()->json([
                'success' => true,
                'created' => false,
                'gallery' => $this->formatGallery($project->gallery),
            ]);
        }

        $galleryName = $this->buildGalleryName($project);

        $gallery = TabloGallery::create([
            'name' => $galleryName,
            'status' => 'active',
            'max_retouch_photos' => 3,
        ]);

        $project->update(['tablo_gallery_id' => $gallery->id]);

        return response()->json([
            'success' => true,
            'created' => true,
            'gallery' => $this->formatGallery($gallery),
        ]);
    }

    /**
     * Upload photos to gallery.
     */
    public function uploadPhotos(UploadGalleryPhotosRequest $request, int $projectId): JsonResponse
    {
        error_log("[GALLERY_UPLOAD] START projectId={$projectId}");

        try {
            $project = $this->getProjectForPartner($projectId);

            if (!$project->tablo_gallery_id) {
                error_log("[GALLERY_UPLOAD] No gallery for project {$projectId}");
                return response()->json([
                    'success' => false,
                    'message' => 'A projektnek nincs galéria hozzárendelve.',
                ], 422);
            }

            $gallery = $project->gallery;
            $files = $request->file('photos');
            error_log("[GALLERY_UPLOAD] Gallery={$gallery->id}, Files=" . count($files));

            $uploadedMedia = collect();
            $zipService = app(MediaZipService::class);

            foreach ($files as $index => $file) {
                error_log("[GALLERY_UPLOAD] Processing file {$index}: {$file->getClientOriginalName()} ({$file->getSize()} bytes)");

                if ($zipService->isZipFile($file)) {
                    $extractedMedia = $zipService->extractAndUpload($file, $gallery, 'photos');
                    $uploadedMedia = $uploadedMedia->merge($extractedMedia);
                } else {
                    $iptcTitle = $this->extractIptcTitle($file->getRealPath());

                    $media = $gallery
                        ->addMedia($file)
                        ->preservingOriginal()
                        ->withCustomProperties(['iptc_title' => $iptcTitle])
                        ->toMediaCollection('photos');

                    error_log("[GALLERY_UPLOAD] Uploaded media id={$media->id} file={$media->file_name}");
                    $uploadedMedia->push($media);
                }
            }

            error_log("[GALLERY_UPLOAD] SUCCESS count={$uploadedMedia->count()}");

            return response()->json([
                'success' => true,
                'message' => "{$uploadedMedia->count()} kép sikeresen feltöltve",
                'uploadedCount' => $uploadedMedia->count(),
                'photos' => $uploadedMedia->map(fn (Media $media) => [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'title' => $media->getCustomProperty('iptc_title', ''),
                    'thumb_url' => $media->getUrl('thumb'),
                    'preview_url' => $media->getUrl('preview'),
                    'original_url' => $media->getUrl(),
                    'size' => $media->size,
                    'createdAt' => $media->created_at->toIso8601String(),
                ])->values()->toArray(),
            ]);
        } catch (\Throwable $e) {
            error_log("[GALLERY_UPLOAD] ERROR: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            error_log("[GALLERY_UPLOAD] TRACE: {$e->getTraceAsString()}");

            return response()->json([
                'success' => false,
                'message' => 'Feltöltési hiba: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple photos from gallery.
     */
    public function deletePhotos(DeleteGalleryPhotosRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'success' => false,
                'message' => 'A projektnek nincs galéria hozzárendelve.',
            ], 422);
        }

        $gallery = $project->gallery;
        $photoIds = array_map('intval', $request->input('photo_ids'));
        $photos = $gallery->getMedia('photos')->whereIn('id', $photoIds);

        $deletedCount = 0;
        foreach ($photos as $media) {
            $media->delete();
            $deletedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} kép sikeresen törölve",
            'deletedCount' => $deletedCount,
        ]);
    }

    /**
     * Delete a single photo from gallery.
     */
    public function deletePhoto(int $projectId, int $mediaId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'success' => false,
                'message' => 'A projektnek nincs galéria hozzárendelve.',
            ], 422);
        }

        $gallery = $project->gallery;
        $media = $gallery->getMedia('photos')->firstWhere('id', $mediaId);

        if (!$media) {
            return response()->json([
                'success' => false,
                'message' => 'A kép nem található ebben a galériában.',
            ], 404);
        }

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kép sikeresen törölve',
        ]);
    }

    /**
     * Get progress data for gallery (student workflow progress).
     */
    public function getProgress(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'totalUsers' => 0,
                'claiming' => 0,
                'retouch' => 0,
                'tablo' => 0,
                'completed' => 0,
                'finalized' => 0,
            ]);
        }

        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $project->tablo_gallery_id)->get();

        $total = $progressRecords->count();
        $finalized = $progressRecords->where('workflow_status', TabloUserProgress::STATUS_FINALIZED)->count();
        $inProgress = $progressRecords->where('workflow_status', TabloUserProgress::STATUS_IN_PROGRESS);

        $stepCounts = [
            'claiming' => 0,
            'retouch' => 0,
            'tablo' => 0,
            'completed' => 0,
        ];

        foreach ($inProgress as $record) {
            $step = $record->current_step ?? 'claiming';
            if (isset($stepCounts[$step])) {
                $stepCounts[$step]++;
            }
        }

        return response()->json([
            'totalUsers' => $total,
            'claiming' => $stepCounts['claiming'],
            'retouch' => $stepCounts['retouch'],
            'tablo' => $stepCounts['tablo'],
            'completed' => $stepCounts['completed'],
            'finalized' => $finalized,
        ]);
    }

    // === PRIVATE HELPERS ===

    /**
     * Format gallery for API response.
     */
    private function formatGallery(TabloGallery $gallery): array
    {
        $photos = $gallery->getMedia('photos');
        $totalSize = $photos->sum('size');

        return [
            'id' => $gallery->id,
            'name' => $gallery->name,
            'photosCount' => $photos->count(),
            'totalSizeMb' => round($totalSize / 1024 / 1024, 2),
            'maxRetouchPhotos' => $gallery->max_retouch_photos,
            'createdAt' => $gallery->created_at->toIso8601String(),
            'photos' => $photos->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'title' => $media->getCustomProperty('iptc_title', ''),
                'thumb_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'original_url' => $media->getUrl(),
                'size' => $media->size,
                'createdAt' => $media->created_at->toIso8601String(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Build a gallery name from project data.
     */
    private function buildGalleryName(TabloProject $project): string
    {
        $parts = [];

        if ($project->school) {
            $parts[] = $project->school->name;
        }

        if ($project->class_name) {
            $parts[] = $project->class_name;
        }

        return implode(' - ', $parts) ?: 'Galéria';
    }

    /**
     * Extract IPTC title from an image file.
     */
    private function extractIptcTitle(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $size = @getimagesize($filePath, $info);

        if ($size === false || !isset($info['APP13'])) {
            return null;
        }

        $iptc = @iptcparse($info['APP13']);

        if ($iptc === false) {
            return null;
        }

        $titleFields = ['2#005', '2#120', '2#105'];

        foreach ($titleFields as $field) {
            if (isset($iptc[$field][0]) && !empty($iptc[$field][0])) {
                $title = $iptc[$field][0];
                $encoding = mb_detect_encoding($title, ['UTF-8', 'ISO-8859-1', 'ISO-8859-2', 'Windows-1252'], true);

                if ($encoding && $encoding !== 'UTF-8') {
                    $title = mb_convert_encoding($title, 'UTF-8', $encoding);
                }

                return trim($title) ?: null;
            }
        }

        return null;
    }
}
