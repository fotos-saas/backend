<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\ExtendExpiryRequest;
use App\Http\Requests\Api\Partner\StoreOrderAlbumRequest;
use App\Http\Requests\Api\Partner\UpdateOrderAlbumRequest;
use App\Models\PartnerAlbum;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Order Album Controller
 *
 * Manages album CRUD and state management for partner clients.
 */
class PartnerOrderAlbumController extends Controller
{
    use PartnerAuthTrait;

    /**
     * List all albums for the partner.
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $clientId = $request->input('client_id');
        $type = $request->input('type');
        $status = $request->input('status');

        $query = PartnerAlbum::byPartner($partnerId)
            ->with('client');

        if ($clientId) {
            $query->byClient((int) $clientId);
        }

        if ($type) {
            $query->ofType($type);
        }

        if ($status) {
            $query->withStatus($status);
        }

        if ($search) {
            // SECURITY: QueryHelper::safeLikePattern használata SQL injection ellen
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhereHas('client', function ($cq) use ($pattern) {
                        $cq->where('name', 'ILIKE', $pattern);
                    });
            });
        }

        $albums = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $albums->getCollection()->transform(fn ($album) => [
            'id' => $album->id,
            'name' => $album->name,
            'type' => $album->type,
            'status' => $album->status,
            'client' => [
                'id' => $album->client->id,
                'name' => $album->client->name,
            ],
            'photosCount' => $album->photos_count,
            'maxSelections' => $album->max_selections,
            'minSelections' => $album->min_selections,
            'expiresAt' => $album->expires_at?->toIso8601String(),
            'finalizedAt' => $album->finalized_at?->toIso8601String(),
            'allowDownload' => $album->allow_download,
            'createdAt' => $album->created_at->toIso8601String(),
        ]);

        return response()->json($albums);
    }

    /**
     * Get a single album with photos.
     */
    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)
            ->with(['client', 'progress'])
            ->findOrFail($id);

        return response()->json([
            'id' => $album->id,
            'name' => $album->name,
            'type' => $album->type,
            'status' => $album->status,
            'client' => [
                'id' => $album->client->id,
                'name' => $album->client->name,
                'email' => $album->client->email,
                'phone' => $album->client->phone,
            ],
            'photos' => $album->getPhotosWithUrls(),
            'photosCount' => $album->photos_count,
            'maxSelections' => $album->max_selections,
            'minSelections' => $album->min_selections,
            'maxRetouchPhotos' => $album->max_retouch_photos,
            'settings' => $album->settings,
            'progress' => $album->progress ? [
                'currentStep' => $album->progress->current_step,
                'stepName' => $album->progress->getStepName(),
                'progressPercent' => $album->progress->getProgressPercentage(),
                'claimedIds' => $album->progress->getClaimedIds(),
                'retouchIds' => $album->progress->getRetouchIds(),
                'tabloId' => $album->progress->getTabloId(),
            ] : null,
            'expiresAt' => $album->expires_at?->toIso8601String(),
            'finalizedAt' => $album->finalized_at?->toIso8601String(),
            'allowDownload' => $album->allow_download,
            'createdAt' => $album->created_at->toIso8601String(),
            'updatedAt' => $album->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Create a new album.
     */
    public function store(StoreOrderAlbumRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Verify client belongs to partner
        $client = PartnerClient::byPartner($partnerId)->find($request->input('client_id'));
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott ügyfél nem a te partnerekhez tartozik.',
            ], 403);
        }

        $album = PartnerAlbum::create([
            'tablo_partner_id' => $partnerId,
            'partner_client_id' => $client->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'status' => PartnerAlbum::STATUS_DRAFT,
            'max_selections' => $request->input('max_selections'),
            'min_selections' => $request->input('min_selections'),
            'max_retouch_photos' => $request->input('max_retouch_photos', 5),
            'expires_at' => now()->addMonth(), // Automatikus 1 hónap lejárat
        ]);

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
     * Update an album.
     */
    public function update(UpdateOrderAlbumRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Can only update draft albums
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
     * Delete an album.
     */
    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Cannot delete completed albums
        if ($album->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Befejezett album nem törölhető.',
            ], 422);
        }

        // Delete all media
        $album->clearMediaCollection('photos');

        $album->delete();

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen törölve',
        ]);
    }

    /**
     * Activate album (change status from draft to claiming).
     */
    public function activate(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        if (!$album->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat státuszú album aktiválható.',
            ], 422);
        }

        // Check if album has photos
        if ($album->photos_count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Az album aktiválásához legalább egy kép feltöltése szükséges.',
            ], 422);
        }

        $album->update(['status' => PartnerAlbum::STATUS_CLAIMING]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen aktiválva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Deactivate album (change status from claiming back to draft).
     */
    public function deactivate(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Only claiming status can be deactivated
        if ($album->status !== PartnerAlbum::STATUS_CLAIMING) {
            return response()->json([
                'success' => false,
                'message' => 'Csak kiválasztás státuszú album deaktiválható.',
            ], 422);
        }

        $album->update(['status' => PartnerAlbum::STATUS_DRAFT]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen deaktiválva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Extend album expiry date.
     */
    public function extendExpiry(ExtendExpiryRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $album->update([
            'expires_at' => $request->input('expires_at'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lejárat sikeresen módosítva',
            'data' => [
                'expiresAt' => $album->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reopen album (change status from completed back to claiming).
     */
    public function reopen(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        // Only completed albums can be reopened
        if ($album->status !== PartnerAlbum::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Csak befejezett album nyitható újra.',
            ], 422);
        }

        $album->update([
            'status' => PartnerAlbum::STATUS_CLAIMING,
            'finalized_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Album sikeresen újranyitva',
            'data' => [
                'id' => $album->id,
                'status' => $album->status,
            ],
        ]);
    }

    /**
     * Toggle download permission for album.
     */
    public function toggleDownload(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($id);

        $album->update([
            'allow_download' => !$album->allow_download,
        ]);

        return response()->json([
            'success' => true,
            'message' => $album->allow_download ? 'Letöltés engedélyezve' : 'Letöltés letiltva',
            'data' => [
                'id' => $album->id,
                'allowDownload' => $album->allow_download,
            ],
        ]);
    }
}
