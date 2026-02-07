<?php

namespace App\Services\Partner;

use App\Helpers\QueryHelper;
use App\Models\PartnerAlbum;
use App\Models\PartnerClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Order Album kezelő service.
 *
 * Partner megrendelési albumok lekérdezése, létrehozása és állapotkezelése.
 */
class OrderAlbumService
{
    /**
     * Albumok listázása szűrőkkel és lapozással.
     */
    public function getFilteredAlbums(int $partnerId, Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $clientId = $request->input('client_id');
        $type = $request->input('type');
        $status = $request->input('status');

        $query = PartnerAlbum::byPartner($partnerId)->with('client');

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
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhereHas('client', function ($cq) use ($pattern) {
                        $cq->where('name', 'ILIKE', $pattern);
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Album lista elem formázása a response-hoz.
     */
    public function formatAlbumListItem(PartnerAlbum $album): array
    {
        return [
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
        ];
    }

    /**
     * Album részletes adatai (show endpoint-hoz).
     */
    public function getAlbumDetail(int $partnerId, int $albumId): array
    {
        $album = PartnerAlbum::byPartner($partnerId)
            ->with(['client', 'progress'])
            ->findOrFail($albumId);

        return [
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
        ];
    }

    /**
     * Új album létrehozása.
     *
     * @return array{album: PartnerAlbum|null, error: string|null, errorCode: int|null}
     */
    public function createAlbum(int $partnerId, array $data): array
    {
        // Ügyfél ellenőrzése - a partnerhez tartozik-e
        $client = PartnerClient::byPartner($partnerId)->find($data['client_id']);
        if (!$client) {
            return [
                'album' => null,
                'error' => 'A megadott ügyfél nem a te partnerekhez tartozik.',
                'errorCode' => 403,
            ];
        }

        $album = PartnerAlbum::create([
            'tablo_partner_id' => $partnerId,
            'partner_client_id' => $client->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => PartnerAlbum::STATUS_DRAFT,
            'max_selections' => $data['max_selections'],
            'min_selections' => $data['min_selections'],
            'max_retouch_photos' => $data['max_retouch_photos'] ?? 5,
            'expires_at' => now()->addMonth(),
        ]);

        return ['album' => $album, 'error' => null, 'errorCode' => null];
    }

    /**
     * Album státuszváltás: aktiválás (draft → claiming).
     *
     * @return array{success: bool, message: string}
     */
    public function activate(PartnerAlbum $album): array
    {
        if (!$album->isDraft()) {
            return ['success' => false, 'message' => 'Csak piszkozat státuszú album aktiválható.'];
        }

        if ($album->photos_count === 0) {
            return ['success' => false, 'message' => 'Az album aktiválásához legalább egy kép feltöltése szükséges.'];
        }

        $album->update(['status' => PartnerAlbum::STATUS_CLAIMING]);

        return ['success' => true, 'message' => 'Album sikeresen aktiválva'];
    }

    /**
     * Album státuszváltás: deaktiválás (claiming → draft).
     *
     * @return array{success: bool, message: string}
     */
    public function deactivate(PartnerAlbum $album): array
    {
        if ($album->status !== PartnerAlbum::STATUS_CLAIMING) {
            return ['success' => false, 'message' => 'Csak kiválasztás státuszú album deaktiválható.'];
        }

        $album->update(['status' => PartnerAlbum::STATUS_DRAFT]);

        return ['success' => true, 'message' => 'Album sikeresen deaktiválva'];
    }

    /**
     * Album státuszváltás: újranyitás (completed → claiming).
     *
     * @return array{success: bool, message: string}
     */
    public function reopen(PartnerAlbum $album): array
    {
        if ($album->status !== PartnerAlbum::STATUS_COMPLETED) {
            return ['success' => false, 'message' => 'Csak befejezett album nyitható újra.'];
        }

        $album->update([
            'status' => PartnerAlbum::STATUS_CLAIMING,
            'finalized_at' => null,
        ]);

        return ['success' => true, 'message' => 'Album sikeresen újranyitva'];
    }

    /**
     * Letöltés engedélyezés/tiltás váltása.
     */
    public function toggleDownload(PartnerAlbum $album): bool
    {
        $album->update(['allow_download' => !$album->allow_download]);

        return $album->allow_download;
    }
}
