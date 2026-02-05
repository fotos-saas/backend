<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Client\ExtendCodeRequest;
use App\Http\Requests\Api\Client\GenerateCodeRequest;
use App\Http\Requests\Api\Client\StoreClientRequest;
use App\Http\Requests\Api\Client\UpdateClientRequest;
use App\Models\PartnerClient;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Client Controller
 *
 * Manages clients for a partner (fotós).
 */
class PartnerClientController extends Controller
{
    use PartnerAuthTrait;

    /**
     * List all clients for the partner.
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');

        $query = PartnerClient::byPartner($partnerId)
            ->withCount('albums');

        if ($search) {
            // SECURITY: QueryHelper::safeLikePattern használata SQL injection ellen
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhere('email', 'ILIKE', $pattern)
                    ->orWhere('phone', 'ILIKE', $pattern);
            });
        }

        $clients = $query->orderBy('name')->paginate($perPage);

        $clients->getCollection()->transform(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'note' => $client->note,
            'accessCode' => $client->access_code,
            'accessCodeEnabled' => $client->access_code_enabled,
            'accessCodeExpiresAt' => $client->access_code_expires_at?->toIso8601String(),
            'lastLoginAt' => $client->last_login_at?->toIso8601String(),
            'albumsCount' => $client->albums_count ?? 0,
            'allowRegistration' => $client->allow_registration,
            'isRegistered' => $client->is_registered,
            'createdAt' => $client->created_at->toIso8601String(),
        ]);

        return response()->json($clients);
    }

    /**
     * Get a single client.
     */
    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)
            ->with(['albums' => function ($q) {
                $q->latest();
            }])
            ->findOrFail($id);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'note' => $client->note,
            'accessCode' => $client->access_code,
            'accessCodeEnabled' => $client->access_code_enabled,
            'accessCodeExpiresAt' => $client->access_code_expires_at?->toIso8601String(),
            'lastLoginAt' => $client->last_login_at?->toIso8601String(),
            'allowRegistration' => $client->allow_registration,
            'isRegistered' => $client->is_registered,
            'albums' => $client->albums->map(function ($album) {
                // Get first 3 photo thumbnails
                $thumbnails = $album->getMedia('photos')
                    ->take(3)
                    ->map(fn ($media) => $media->getUrl('thumb'))
                    ->toArray();

                return [
                    'id' => $album->id,
                    'name' => $album->name,
                    'type' => $album->type,
                    'status' => $album->status,
                    'photosCount' => $album->photos_count,
                    'thumbnails' => $thumbnails,
                    'expiresAt' => $album->expires_at?->toIso8601String(),
                    'allowDownload' => $album->allow_download,
                    'createdAt' => $album->created_at->toIso8601String(),
                ];
            }),
            'createdAt' => $client->created_at->toIso8601String(),
            'updatedAt' => $client->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Create a new client.
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::create([
            'tablo_partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ügyfél sikeresen létrehozva',
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'note' => $client->note,
                'accessCode' => $client->access_code,
                'accessCodeEnabled' => $client->access_code_enabled,
            ],
        ], 201);
    }

    /**
     * Update a client.
     */
    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $updateData = [
            'name' => $request->input('name', $client->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ];

        // Regisztráció engedélyezés
        if ($request->has('allow_registration')) {
            $updateData['allow_registration'] = $request->boolean('allow_registration');
        }

        $client->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Ügyfél sikeresen frissítve',
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'note' => $client->note,
                'allowRegistration' => $client->allow_registration,
                'isRegistered' => $client->is_registered,
            ],
        ]);
    }

    /**
     * Delete a client.
     */
    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        // Check if client has albums
        if ($client->albums()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Az ügyfél nem törölhető, mert van hozzá tartozó album.',
            ], 422);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ügyfél sikeresen törölve',
        ]);
    }

    /**
     * Generate access code for a client.
     */
    public function generateCode(GenerateCodeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        // Generate new unique code
        $code = $client->generateAccessCode();

        // Default: 1 hónap lejárat
        $expiresAt = $request->input('expires_at')
            ? \Carbon\Carbon::parse($request->input('expires_at'))
            : \Carbon\Carbon::now()->addMonth();

        $client->update([
            'access_code' => $code,
            'access_code_enabled' => true,
            'access_code_expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Belépési kód sikeresen generálva',
            'data' => [
                'accessCode' => $client->access_code,
                'accessCodeEnabled' => $client->access_code_enabled,
                'accessCodeExpiresAt' => $client->access_code_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Extend access code expiry for a client.
     */
    public function extendCode(ExtendCodeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $client->update([
            'access_code_expires_at' => \Carbon\Carbon::parse($request->input('expires_at')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lejárat sikeresen módosítva',
            'data' => [
                'accessCodeExpiresAt' => $client->access_code_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Disable access code for a client.
     */
    public function disableCode(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $client->update([
            'access_code_enabled' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Belépési kód inaktiválva',
        ]);
    }
}
