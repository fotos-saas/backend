<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Services\NameMatcherService;
use App\Services\PartnerPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Photo Controller - Photo upload and matching for partners.
 *
 * Handles: bulkUploadPhotos(), getPendingPhotos(), deletePendingPhotos(),
 *          matchPhotos(), assignPhotos(), assignToTalon(),
 *          uploadPersonPhoto(), getTalonPhotos()
 */
class PartnerPhotoController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Bulk upload photos (images or ZIP).
     * @deprecated Use PartnerAlbumController::uploadToAlbum() instead
     */
    public function bulkUploadPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480', // max 20MB per file
            'zip' => 'required_without:photos|file|mimes:zip|max:524288', // max 512MB
            'album' => 'nullable|string|in:students,teachers',
        ], [
            'photos.required_without' => 'Képek vagy ZIP fájl megadása kötelező.',
            'photos.max' => 'Maximum 50 kép tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 20MB.',
            'zip.mimes' => 'Csak ZIP fájl engedélyezett.',
            'zip.max' => 'Maximum ZIP méret: 512MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        $uploadedMedia = collect();

        // Album - alapértelmezetten 'students'
        $album = $request->input('album', 'students');

        // ZIP feltöltés
        if ($request->hasFile('zip')) {
            $uploadedMedia = $photoService->uploadFromZip($project, $request->file('zip'), $album);
        }
        // Egyedi képek
        elseif ($request->hasFile('photos')) {
            $uploadedMedia = $photoService->bulkUpload($project, $request->file('photos'), $album);
        }

        return response()->json([
            'success' => true,
            'uploadedCount' => $uploadedMedia->count(),
            'album' => $album,
            'photos' => $uploadedMedia->map(fn ($media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
            ])->values(),
        ]);
    }

    /**
     * Get pending photos (uploaded but not yet matched).
     */
    public function getPendingPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        return response()->json([
            'photos' => $photoService->getPendingPhotos($project),
        ]);
    }

    /**
     * Delete pending photos by media IDs.
     */
    public function deletePendingPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen adatok.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mediaIds = array_map('intval', $request->input('media_ids'));

        // Töröljük a tablo_pending collection-ből a megadott média rekordokat
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
     * Match photos with missing persons using AI.
     */
    public function matchPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Pending képek lekérdezése
        $photos = $project->getMedia('tablo_pending');

        // Ha van szűrés média ID-kra
        if ($request->filled('photoIds')) {
            $photoIds = array_map('intval', $request->input('photoIds', []));
            // Collection filter - a whereIn nem működik jól Spatie Media objektumokon
            $photos = $photos->filter(fn ($m) => in_array($m->id, $photoIds, true));
        }

        if ($photos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincsenek feltöltött képek a párosításhoz.',
            ], 400);
        }

        // Még párosítatlan személyek
        $persons = $project->missingPersons()
            ->whereNull('media_id')
            ->orderBy('position')
            ->get();

        if ($persons->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs párosítatlan személy a listában.',
            ], 400);
        }

        // Fájlok összeállítása a matcher-hez
        $files = $photos->map(fn ($m) => [
            'filename' => $m->file_name,
            'title' => $m->getCustomProperty('iptc_title'),
            'mediaId' => $m->id,
        ])->values()->toArray();

        $names = $persons->pluck('name')->toArray();

        // AI párosítás
        /** @var NameMatcherService $matcherService */
        $matcherService = app(NameMatcherService::class);

        try {
            $result = $matcherService->match($names, $files);

            return response()->json([
                'success' => true,
                'matches' => $result->matches,
                'uncertain' => $result->uncertain,
                'unmatchedNames' => $result->unmatchedNames,
                'unmatchedFiles' => $result->unmatchedFiles,
                'summary' => $result->getSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI párosítás sikertelen: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign photos to missing persons (finalize matching).
     */
    public function assignPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.personId' => 'required|integer',
            'assignments.*.mediaId' => 'required|integer',
        ], [
            'assignments.required' => 'Legalább egy párosítás megadása kötelező.',
            'assignments.*.personId.required' => 'Személy ID megadása kötelező.',
            'assignments.*.mediaId.required' => 'Média ID megadása kötelező.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        $assignedCount = $photoService->assignPhotos(
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
     * Move photos to talon (skip matching).
     */
    public function assignToTalon(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ], [
            'mediaIds.required' => 'Legalább egy kép ID megadása kötelező.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        $movedCount = $photoService->moveToTalon(
            $project,
            array_map('intval', $request->input('mediaIds'))
        );

        return response()->json([
            'success' => true,
            'movedCount' => $movedCount,
            'message' => "{$movedCount} kép átmozgatva a talonba.",
        ]);
    }

    /**
     * Upload photo for a specific missing person.
     */
    public function uploadPersonPhoto(int $projectId, int $personId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $person = $project->missingPersons()->find($personId);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'A személy nem található.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
        ], [
            'photo.required' => 'Kép megadása kötelező.',
            'photo.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photo.max' => 'Maximum fájlméret: 20MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        $media = $photoService->uploadPersonPhoto($person, $request->file('photo'));

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
     * Get talon photos.
     */
    public function getTalonPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerPhotoService $photoService */
        $photoService = app(PartnerPhotoService::class);

        return response()->json([
            'photos' => $photoService->getTalonPhotos($project),
        ]);
    }
}
