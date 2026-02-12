<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\CreateGalleryAction;
use App\Actions\Partner\DeleteGalleryPhotosAction;
use App\Actions\Partner\GetGalleryProgressAction;
use App\Actions\Partner\UploadGalleryPhotosAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Gallery\DeleteGalleryPhotosRequest;
use App\Http\Requests\Gallery\SetGalleryDeadlineRequest;
use App\Http\Requests\Gallery\UploadGalleryPhotosRequest;
use Illuminate\Http\JsonResponse;

/**
 * Partner Gallery Controller
 *
 * Galéria CRUD és képfeltöltés kezelése partner projektekhez.
 * Az üzleti logika Action osztályokba van kiemelve.
 */
class PartnerGalleryController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly CreateGalleryAction $createGalleryAction,
        private readonly UploadGalleryPhotosAction $uploadPhotosAction,
        private readonly DeleteGalleryPhotosAction $deletePhotosAction,
        private readonly GetGalleryProgressAction $getProgressAction,
    ) {}

    /**
     * Galéria adatok lekérdezése egy projekthez.
     */
    public function getGallery(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'hasGallery' => false,
                'gallery' => null,
                'deadline' => $project->deadline?->toDateString(),
            ]);
        }

        return response()->json([
            'hasGallery' => true,
            'gallery' => $this->createGalleryAction->formatGallery($project->gallery),
            'deadline' => $project->deadline?->toDateString(),
        ]);
    }

    /**
     * Galéria létrehozása vagy meglévő lekérdezése.
     */
    public function createOrGetGallery(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $result = $this->createGalleryAction->execute($project);

        return response()->json($result);
    }

    /**
     * Képek feltöltése a galériába.
     */
    public function uploadPhotos(UploadGalleryPhotosRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        if (!$project->tablo_gallery_id) {
            return response()->json([
                'success' => false,
                'message' => 'A projektnek nincs galéria hozzárendelve.',
            ], 422);
        }

        $files = $request->file('photos');
        $result = $this->uploadPhotosAction->execute($project->gallery, $files);

        return response()->json($result);
    }

    /**
     * Több kép törlése a galériából.
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

        $photoIds = array_map('intval', $request->input('photo_ids'));
        $result = $this->deletePhotosAction->executeMany($project->gallery, $photoIds);

        return response()->json($result);
    }

    /**
     * Egyetlen kép törlése a galériából.
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

        $result = $this->deletePhotosAction->executeOne($project->gallery, $mediaId);
        $status = $result['status'] ?? 200;
        unset($result['status']);

        return response()->json($result, $status);
    }

    /**
     * Határidő beállítása a projekthez.
     */
    public function setDeadline(SetGalleryDeadlineRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $project->update(['deadline' => $request->validated('deadline')]);

        return response()->json([
            'success' => true,
            'message' => 'Határidő sikeresen beállítva',
            'data' => [
                'deadline' => $project->deadline?->toDateString(),
            ],
        ]);
    }

    /**
     * Galéria haladás statisztika (diák workflow állapotok).
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

        $result = $this->getProgressAction->execute($project->tablo_gallery_id);

        return response()->json($result);
    }
}
