<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\MatchPhotosAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\AssignPhotosRequest;
use App\Http\Requests\Api\Partner\AssignToTalonRequest;
use App\Http\Requests\Api\Partner\DeletePendingPhotosRequest;
use App\Http\Requests\Api\Partner\MatchPhotosRequest;
use App\Http\Requests\Api\Partner\UploadPersonPhotoRequest;
use App\Services\PartnerPhotoService;
use Illuminate\Http\JsonResponse;

/**
 * Partner Photo Controller - Fotó feltöltés és párosítás.
 *
 * Metódusok: getPendingPhotos(), deletePendingPhotos(),
 *            matchPhotos(), assignPhotos(), assignToTalon(),
 *            uploadPersonPhoto(), getTalonPhotos()
 */
class PartnerPhotoController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private PartnerPhotoService $photoService
    ) {}

    /**
     * Függő (még nem párosított) képek lekérdezése.
     */
    public function getPendingPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        return response()->json([
            'photos' => $this->photoService->getPendingPhotos($project),
        ]);
    }

    /**
     * Függő képek törlése média ID-k alapján.
     */
    public function deletePendingPhotos(int $projectId, DeletePendingPhotosRequest $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $mediaIds = $request->input('media_ids');

        $deletedCount = $project->getMedia('tablo_pending')
            ->filter(fn ($m) => in_array($m->id, $mediaIds, true))
            ->each(fn ($m) => $m->delete())
            ->count();

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} kép törölve.",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Képek AI párosítása a hiányzó személyekkel.
     */
    public function matchPhotos(int $projectId, MatchPhotosRequest $request, MatchPhotosAction $action): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $result = $action->execute($project, $request->input('photoIds'));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json($result);
    }

    /**
     * Fotók hozzárendelése személyekhez (párosítás véglegesítése).
     */
    public function assignPhotos(int $projectId, AssignPhotosRequest $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $assignedCount = $this->photoService->assignPhotos(
            $project,
            $request->input('assignments')
        );

        return response()->json([
            'success' => true,
            'assignedCount' => $assignedCount,
            'message' => "{$assignedCount} kép sikeresen párosítva.",
        ]);
    }

    /**
     * Képek áthelyezése a talonba (párosítás kihagyása).
     */
    public function assignToTalon(int $projectId, AssignToTalonRequest $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $movedCount = $this->photoService->moveToTalon(
            $project,
            $request->input('mediaIds')
        );

        return response()->json([
            'success' => true,
            'movedCount' => $movedCount,
            'message' => "{$movedCount} kép átmozgatva a talonba.",
        ]);
    }

    /**
     * Fotó feltöltése egy adott személyhez.
     */
    public function uploadPersonPhoto(int $projectId, int $personId, UploadPersonPhotoRequest $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $person = $project->persons()->find($personId);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'A személy nem található.',
            ], 404);
        }

        $media = $this->photoService->uploadPersonPhoto($person, $request->file('photo'));

        return response()->json([
            'success' => true,
            'message' => 'Kép sikeresen feltöltve.',
            'photo' => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'thumbUrl' => $media->getUrl('thumb'),
                'version' => $media->getCustomProperty('version'),
            ],
        ]);
    }

    /**
     * Talon képek lekérdezése.
     */
    public function getTalonPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        return response()->json([
            'photos' => $this->photoService->getTalonPhotos($project),
        ]);
    }
}
