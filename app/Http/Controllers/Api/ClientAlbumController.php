<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerAlbum;
use App\Models\PartnerAlbumProgress;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     *
     * GET /api/client/albums
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
     *
     * GET /api/client/albums/{id}
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

        // Get progress or create default
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
     *
     * POST /api/client/albums/{id}/selection
     *
     * Body (selection type):
     * { "selected_ids": [1, 2, 3] }
     *
     * Body (tablo type):
     * { "step": "claiming|retouch|tablo", "ids": [1, 2, 3] }
     * - For tablo step, "ids" should be a single ID: { "step": "tablo", "ids": [5] }
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

        // Check if album is already completed
        if ($album->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Ez az album már le van zárva.',
            ], 422);
        }

        // Selection type album - simple selection
        if ($album->isSelectionType()) {
            return $this->saveSimpleSelection($request, $album, $client);
        }

        // Tablo type album - multi-step workflow
        return $this->saveTabloSelection($request, $album, $client);
    }

    /**
     * Save simple selection (selection type album).
     */
    private function saveSimpleSelection(Request $request, PartnerAlbum $album, PartnerClient $client): JsonResponse
    {
        $validated = $request->validate([
            'selected_ids' => ['required', 'array'],
            'selected_ids.*' => ['required', 'integer'],
            'finalize' => ['boolean'],
        ]);

        $selectedIds = array_map('intval', $validated['selected_ids']);
        $finalize = $validated['finalize'] ?? false;

        // Validate selection count - CSAK véglegesítéskor!
        if ($finalize) {
            if ($album->min_selections && count($selectedIds) < $album->min_selections) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum {$album->min_selections} képet kell kiválasztanod.",
                ], 422);
            }
        }

        // Max korlátozás mindig érvényes
        if ($album->max_selections && count($selectedIds) > $album->max_selections) {
            return response()->json([
                'success' => false,
                'message' => "Maximum {$album->max_selections} képet választhatsz ki.",
            ], 422);
        }

        // Validate that all IDs exist in album
        $albumMediaIds = $album->getMedia('photos')->pluck('id')->toArray();
        $invalidIds = array_diff($selectedIds, $albumMediaIds);

        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Néhány kiválasztott kép nem tartozik ehhez az albumhoz.',
            ], 422);
        }

        // Get or create progress
        $progress = $this->getOrCreateProgress($album, $client);

        // Save selection
        $progress->setClaimedIds($selectedIds);

        // Finalize if requested
        if ($finalize) {
            $album->finalize();
        }

        return response()->json([
            'success' => true,
            'message' => $finalize ? 'Választás sikeresen véglegesítve!' : 'Választás mentve.',
            'data' => [
                'selectedCount' => count($selectedIds),
                'isCompleted' => $album->fresh()->isCompleted(),
            ],
        ]);
    }

    /**
     * Save tablo workflow selection.
     */
    private function saveTabloSelection(Request $request, PartnerAlbum $album, PartnerClient $client): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'string', 'in:claiming,retouch,tablo'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'finalize' => ['boolean'],
        ]);

        $step = $validated['step'];
        $ids = array_map('intval', $validated['ids']);
        $finalize = $validated['finalize'] ?? false;

        // Validate IDs belong to album
        $albumMediaIds = $album->getMedia('photos')->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $albumMediaIds);

        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Néhány kiválasztott kép nem tartozik ehhez az albumhoz.',
            ], 422);
        }

        // Get or create progress
        $progress = $this->getOrCreateProgress($album, $client);

        // Validate step order
        $stepOrder = ['claiming', 'retouch', 'tablo'];
        $currentStepIndex = array_search($progress->current_step, $stepOrder);
        $requestedStepIndex = array_search($step, $stepOrder);

        if ($requestedStepIndex > $currentStepIndex + 1) {
            return response()->json([
                'success' => false,
                'message' => 'Először az előző lépést kell befejezned.',
            ], 422);
        }

        // Validate step-specific rules
        switch ($step) {
            case 'claiming':
                // Minimum CSAK véglegesítéskor ellenőrizzük
                if ($finalize && $album->min_selections && count($ids) < $album->min_selections) {
                    return response()->json([
                        'success' => false,
                        'message' => "Minimum {$album->min_selections} képet kell kiválasztanod.",
                    ], 422);
                }
                // Max korlátozás mindig érvényes
                if ($album->max_selections && count($ids) > $album->max_selections) {
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$album->max_selections} képet választhatsz ki.",
                    ], 422);
                }
                $progress->setClaimedIds($ids);
                break;

            case 'retouch':
                // Retouch must be from claimed
                $claimedIds = $progress->getClaimedIds();
                $invalidRetouch = array_diff($ids, $claimedIds);
                if (!empty($invalidRetouch)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Retusra csak a kiválasztott képeket jelölheted.',
                    ], 422);
                }
                if ($album->max_retouch_photos && count($ids) > $album->max_retouch_photos) {
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$album->max_retouch_photos} képet jelölhetsz retusra.",
                    ], 422);
                }
                $progress->setRetouchIds($ids);
                break;

            case 'tablo':
                // Tablo must be single from claimed
                if (count($ids) !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pontosan egy tablóképet kell választanod.',
                    ], 422);
                }
                $claimedIds = $progress->getClaimedIds();
                if (!in_array($ids[0], $claimedIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A tablókép csak a kiválasztott képek közül választható.',
                    ], 422);
                }
                $progress->setTabloId($ids[0]);
                break;
        }

        // Advance step if needed
        if ($requestedStepIndex > $currentStepIndex) {
            $progress->update(['current_step' => $step]);
        }

        // Update album status based on step
        $statusMap = [
            'claiming' => PartnerAlbum::STATUS_CLAIMING,
            'retouch' => PartnerAlbum::STATUS_RETOUCH,
            'tablo' => PartnerAlbum::STATUS_TABLO,
        ];
        if ($album->status !== $statusMap[$step]) {
            $album->update(['status' => $statusMap[$step]]);
        }

        // Finalize if requested and on tablo step
        if ($finalize && $step === 'tablo') {
            $album->finalize();
        }

        return response()->json([
            'success' => true,
            'message' => $finalize ? 'Választás sikeresen véglegesítve!' : 'Lépés mentve.',
            'data' => [
                'currentStep' => $progress->current_step,
                'percentage' => $progress->getProgressPercentage(),
                'isCompleted' => $album->fresh()->isCompleted(),
            ],
        ]);
    }

    /**
     * Get or create progress for album.
     */
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

    /**
     * Format album for API response.
     */
    private function formatAlbum(PartnerAlbum $album, int $clientId): array
    {
        $progress = $album->progress;

        // Get first 4 thumbnail URLs for preview stack
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
            // Download fields
            'canDownload' => $album->canDownload(),
            'downloadDaysRemaining' => $album->getDownloadDaysRemaining(),
        ];
    }

    /**
     * Get human-readable status name.
     */
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
