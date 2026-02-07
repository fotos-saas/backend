<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\ChangeClientPasswordRequest;
use App\Http\Requests\Api\Client\LoginClientRequest;
use App\Http\Requests\Api\Client\RegisterClientRequest;
use App\Models\PartnerClient;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientAuthController extends Controller
{
    /**
     * Register client with email and password
     */
    public function register(RegisterClientRequest $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs bejelentkezve.',
            ], 401);
        }

        if ($client->is_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Már regisztrálva vagy.',
            ], 422);
        }

        if (!$client->hasAlbumWithRegistrationAllowed()) {
            return response()->json([
                'success' => false,
                'message' => 'A regisztráció nem engedélyezett.',
            ], 403);
        }

        if ($client->email !== $request->input('email')) {
            $client->email = $request->input('email');
        }

        $client->register($request->input('password'));

        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        DB::table('personal_access_tokens')
            ->where('partner_client_id', $client->id)
            ->delete();

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => PartnerClient::class,
            'tokenable_id' => $client->id,
            'name' => 'client-registered-token',
            'token' => $hashedToken,
            'abilities' => json_encode(['client', 'registered']),
            'partner_client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sikeres regisztráció! Mostantól email és jelszóval léphetsz be.',
            'token' => $plainTextToken,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'isRegistered' => true,
            ],
        ]);
    }

    /**
     * Login with email and password
     */
    public function login(LoginClientRequest $request): JsonResponse
    {
        $client = PartnerClient::registered()
            ->byEmail($request->input('email'))
            ->with('partner')
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Hibás email vagy jelszó.',
            ], 401);
        }

        if (!Hash::check($request->input('password'), $client->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Hibás email vagy jelszó.',
            ], 401);
        }

        if (!$client->partner || !$client->partner->hasFeature('client_orders')) {
            return response()->json([
                'success' => false,
                'message' => 'A funkció nem elérhető.',
            ], 403);
        }

        $client->recordLogin();

        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => PartnerClient::class,
            'tokenable_id' => $client->id,
            'name' => 'client-registered-token',
            'token' => $hashedToken,
            'abilities' => json_encode(['client', 'registered']),
            'partner_client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $albums = $client->albums()
            ->where('status', '!=', 'draft')
            ->latest()
            ->get()
            ->map(fn ($album) => $album->toClientArray(includeDownload: true));

        $response = [
            'success' => true,
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'type' => 'partner-client',
                'isRegistered' => true,
            ],
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'wantsNotifications' => $client->wants_notifications,
            ],
            'albums' => $albums,
            'token' => $plainTextToken,
            'tokenType' => 'client',
            'loginType' => 'client',
        ];

        TabloPartner::appendBranding($response, $client->partner);

        return response()->json($response);
    }

    /**
     * Get current client profile
     */
    public function profile(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs bejelentkezve.',
            ], 401);
        }

        $data = [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'isRegistered' => $client->is_registered,
            'registeredAt' => $client->registered_at?->toIso8601String(),
            'wantsNotifications' => $client->wants_notifications,
            'canRegister' => !$client->is_registered && $client->hasAlbumWithRegistrationAllowed(),
        ];

        TabloPartner::appendBranding($data, $client->partner);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client || !$client->is_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Csak regisztrált ügyfelek módosíthatják az értesítési beállításokat.',
            ], 403);
        }

        $validated = $request->validate([
            'wants_notifications' => ['required', 'boolean'],
        ]);

        $client->update([
            'wants_notifications' => $validated['wants_notifications'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Értesítési beállítások frissítve.',
            'data' => [
                'wantsNotifications' => $client->wants_notifications,
            ],
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(ChangeClientPasswordRequest $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client || !$client->is_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Csak regisztrált ügyfelek változtathatják meg a jelszavukat.',
            ], 403);
        }

        if (!Hash::check($request->input('current_password'), $client->password)) {
            return response()->json([
                'success' => false,
                'message' => 'A jelenlegi jelszó hibás.',
            ], 422);
        }

        $client->update([
            'password' => $request->input('password'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jelszó sikeresen megváltoztatva.',
        ]);
    }
}
