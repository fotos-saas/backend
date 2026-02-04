<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Services\PartnerAlbumService;
use App\Services\PartnerPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Album Controller - Album management for partners.
 *
 * Handles: getAlbums(), getAlbum(), uploadToAlbum(), clearAlbum()
 */
class PartnerAlbumController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Get albums summary (both students and teachers).
     */
    public function getAlbums(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerAlbumService $albumService */
        $albumService = app(PartnerAlbumService::class);

        // Árva képek automatikus migrálása
        $albumService->migrateOrphanPhotos($project);

        return response()->json([
            'albums' => $albumService->getAlbumsSummary($project),
        ]);
    }

    /**
     * Get single album details (photos + missing persons).
     */
    public function getAlbum(int $projectId, string $album): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerAlbumService $albumService */
        $albumService = app(PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $details = $albumService->getAlbumDetails($project, $album);

        return response()->json([
            'album' => $details,
        ]);
    }

    /**
     * Upload photos to a specific album.
     */
    public function uploadToAlbum(int $projectId, string $album, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerAlbumService $albumService */
        $albumService = app(PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480', // max 20MB per file
            'zip' => 'required_without:photos|file|mimes:zip|max:524288', // max 512MB
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
     * Clear all photos from an album.
     */
    public function clearAlbum(int $projectId, string $album): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var PartnerAlbumService $albumService */
        $albumService = app(PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $deletedCount = $albumService->clearAlbum($project, $album);

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} kép törölve az albumból.",
            'deletedCount' => $deletedCount,
        ]);
    }
}
