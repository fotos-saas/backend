<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\ExtendExpiryRequest;
use App\Http\Requests\Api\Partner\StoreOrderAlbumRequest;
use App\Http\Requests\Api\Partner\UpdateOrderAlbumRequest;
use App\Models\PartnerAlbum;
use App\Services\Partner\OrderAlbumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Order Album Controller
 *
 * Album CRUD és állapotkezelés partner ügyfelekhez.
 * Üzleti logika: OrderAlbumService.
 */
class PartnerOrderAlbumController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly OrderAlbumService $albumService,
    ) {}

    /**
     * Albumok listázása.
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $albums = $this->albumService->getFilteredAlbums($partnerId, $request);

        $albums->getCollection()->transform(
            fn ($album) => $this->albumService->formatAlbumListItem($album)
        );

        return response()->json($albums);
    }

    /**
     * Album részletes adatai.
     */
    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        return response()->json(
            $this->albumService->getAlbumDetail($partnerId, $id)
        );
    }

    /**
     * Új album létrehozása.
     */
    public function store(StoreOrderAlbumRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $result = $this->albumService->createAlbum($partnerId, $request->validated());

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], $result['errorCode']);
        }

        $album = $result['album'];

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen létrehozva',
            'data' => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
                'clientId' => $album->partner_client_id,
                'expiresAt' => $album->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Album módosítása.
     */
    public function update(UpdateOrderAlbumRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú album módosítható.',
            ], 422);
        }

        $album->update([
            'name' => $request->input('name', $album->name),
            'max_selections' => $request->input('max_selections', $album->max_selections),
            'min_selections' => $request->input('min_selections', $album->min_selections),
            'max_retouch_photos' => $request->input('max_retouch_photos', $album->max_retouch_photos),
            'status' => $request->input('status', $album->status),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen frissítve',
            'data' => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Album törlése.
     */
    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        if ($album->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Befejezett album nem törölhető.',
            ], 422);
        }

        $album->clearMediaCollection('photos');
        $album->delete();

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen törölve',
        ]);
    }

    /**
     * Album aktiválása (draft → claiming).
     */
    public function activate(int $id): JsonResponse
    {
        $album = $this->findAlbumForPartner($id);
        $result = $this->albumService->activate($album);

        return $this->stateChangeResponse($result, $album);
    }

    /**
     * Album deaktiválása (claiming → draft).
     */
    public function deactivate(int $id): JsonResponse
    {
        $album = $this->findAlbumForPartner($id);
        $result = $this->albumService->deactivate($album);

        return $this->stateChangeResponse($result, $album);
    }

    /**
     * Album lejárat meghosszabbítása.
     */
    public function extendExpiry(ExtendExpiryRequest $request, int $id): JsonResponse
    {
        $album = $this->findAlbumForPartner($id);

        $album->update(['expires_at' => $request->input('expires_at')]);

        return response()->json([
            'success' => true,
            'message' => 'Lejárat sikeresen módosítva',
            'data' => [
                'expiresAt' => $album->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Album újranyitása (completed → claiming).
     */
    public function reopen(int $id): JsonResponse
    {
        $album = $this->findAlbumForPartner($id);
        $result = $this->albumService->reopen($album);

        return $this->stateChangeResponse($result, $album);
    }

    /**
     * Letöltés engedélyezés/tiltás váltása.
     */
    public function toggleDownload(int $id): JsonResponse
    {
        $album = $this->findAlbumForPartner($id);
        $allowDownload = $this->albumService->toggleDownload($album);

        return response()->json([
            'success' => true,
            'message' => $allowDownload ? 'Letöltés engedélyezve' : 'Letöltés letiltva',
            'data' => [
                'id' => $album->id,
                'allowDownload' => $allowDownload,
            ],
        ]);
    }

    // ============================================
    // PRIVATE HELPER METÓDUSOK
    // ============================================

    /**
     * Album lekérése a partner scope-jával.
     */
    private function findAlbumForPartner(int $albumId): PartnerAlbum
    {
        $partnerId = $this->getPartnerIdOrFail();

        return PartnerAlbum::byPartner($partnerId)->findOrFail($albumId);
    }

    /**
     * Egységes válasz státuszváltásokhoz.
     */
    private function stateChangeResponse(array $result, PartnerAlbum $album): JsonResponse
    {
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }
}
