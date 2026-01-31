<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerClient;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Client Controller
 *
 * Manages clients for a partner (fotós).
 */
class PartnerClientController extends Controller
{
    /**
     * Get the authenticated user's partner ID or fail with 403.
     */
    private function getPartnerIdOrFail(): int
    {
        $partnerId = auth()->user()->tablo_partner_id;

        if (!$partnerId) {
            abort(403, 'Nincs partnerhez rendelve');
        }

        return $partnerId;
    }

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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
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
    public function store(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'A név megadása kötelező.',
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
    public function update(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'allow_registration' => 'boolean',
        ], [
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
    public function generateCode(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'expires_at' => 'nullable|date|after:now',
        ], [
            'expires_at.date' => 'Érvénytelen dátum.',
            'expires_at.after' => 'A lejárati dátumnak a jövőben kell lennie.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
    public function extendCode(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $client = PartnerClient::byPartner($partnerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'expires_at' => 'required|date|after:now',
        ], [
            'expires_at.required' => 'A lejárati dátum megadása kötelező.',
            'expires_at.date' => 'Érvénytelen dátum.',
            'expires_at.after' => 'A lejárati dátumnak a jövőben kell lennie.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
