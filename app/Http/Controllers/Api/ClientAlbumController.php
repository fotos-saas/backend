<?php

namespace App\Http\Controllers\Api;

use App\Actions\Client\SaveSimpleSelectionAction;
use App\Actions\Client\SaveTabloSelectionAction;
use App\Http\Controllers\Controller;
use App\Models\PartnerAlbum;
use App\Models\PartnerAlbumProgress;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client Album Controller
 *
 * API endpoints for partner clients to view and select photos from their albums.
 * All endpoints require auth.client middleware (AuthenticateClient).
 */
class ClientAlbumController extends Controller
{
    /**
     * Get all albums for the authenticated client.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $albums = PartnerAlbum::byClient($client->id)
            ->where('status', '!=', PartnerAlbum::STATUS_DRAFT)
            ->with(['progress', 'media'])
            ->latest()
            ->get()
            ->map(fn ($album) => $this->formatAlbum($album, $client->id));

        return response()->json([
            'success' => true,
            'data' => $albums,
        ]);
    }

    /**
     * Get album details with photos.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $album = PartnerAlbum::byClient($client->id)
            ->where('id', $id)
            ->with('progress')
            ->first();

        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Az album nem található.',
            ], 404);
        }

        $progress = $album->progress ?? $this->getOrCreateProgress($album, $client);

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->formatAlbum($album, $client->id),
                'photos' => $album->getPhotosWithUrls(),
                'progress' => [
                    'currentStep' => $progress->current_step,
                    'claimedIds' => $progress->getClaimedIds(),
                    'retouchIds' => $progress->getRetouchIds(),
                    'tabloId' => $progress->getTabloId(),
                    'percentage' => $progress->getProgressPercentage(),
                    'stepName' => $progress->getStepName(),
                ],
            ],
        ]);
    }

    /**
     * Save selection for an album.
     */
    public function saveSelection(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $album = PartnerAlbum::byClient($client->id)
            ->where('id', $id)
            ->first();

        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Az album nem található.',
            ], 404);
        }

        if ($album->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Ez az album már le van zárva.',
            ], 422);
        }

        if ($album->isSelectionType()) {
            return app(SaveSimpleSelectionAction::class)->execute($request, $album, $client);
        }

        return app(SaveTabloSelectionAction::class)->execute($request, $album, $client);
    }

    private function getOrCreateProgress(PartnerAlbum $album, PartnerClient $client): PartnerAlbumProgress
    {
        return PartnerAlbumProgress::firstOrCreate(
            [
                'partner_album_id' => $album->id,
                'partner_client_id' => $client->id,
            ],
            [
                'current_step' => PartnerAlbumProgress::STEP_CLAIMING,
                'steps_data' => PartnerAlbumProgress::getDefaultStepsData(),
            ]
        );
    }

    private function formatAlbum(PartnerAlbum $album, int $clientId): array
    {
        $progress = $album->progress;

        $previewThumbs = $album->getMedia('photos')
            ->take(4)
            ->map(fn ($media) => $media->getUrl('thumb'))
            ->values()
            ->toArray();

        return [
            'id' => $album->id,
            'name' => $album->name,
            'type' => $album->type,
            'typeName' => $album->isSelectionType() ? 'Képválasztás' : 'Tablókép',
            'status' => $album->status,
            'statusName' => $this->getStatusName($album->status),
            'photosCount' => $album->photos_count,
            'maxSelections' => $album->max_selections,
            'minSelections' => $album->min_selections,
            'maxRetouchPhotos' => $album->max_retouch_photos,
            'isCompleted' => $album->isCompleted(),
            'isDraft' => $album->isDraft(),
            'finalizedAt' => $album->finalized_at?->toIso8601String(),
            'createdAt' => $album->created_at->toIso8601String(),
            'previewThumbs' => $previewThumbs,
            'progress' => $progress ? [
                'currentStep' => $progress->current_step,
                'percentage' => $progress->getProgressPercentage(),
                'stepName' => $progress->getStepName(),
                'selectedCount' => count($progress->getClaimedIds()),
            ] : null,
            'canDownload' => $album->canDownload(),
            'downloadDaysRemaining' => $album->getDownloadDaysRemaining(),
        ];
    }

    private function getStatusName(string $status): string
    {
        return match ($status) {
            PartnerAlbum::STATUS_DRAFT => 'Vázlat',
            PartnerAlbum::STATUS_CLAIMING => 'Képválasztás',
            PartnerAlbum::STATUS_RETOUCH => 'Retusálás',
            PartnerAlbum::STATUS_TABLO => 'Tablókép',
            PartnerAlbum::STATUS_COMPLETED => 'Lezárva',
            default => 'Ismeretlen',
        };
    }
}
