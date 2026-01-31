<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Client Auth Controller
 *
 * Kliens regisztráció és email/jelszó alapú bejelentkezés kezelése.
 *
 * FONTOS: Ha a kliens regisztrál, a kód alapú belépés MEGSZŰNIK,
 * és csak email/jelszóval léphet be.
 */
class ClientAuthController extends Controller
{
    /**
     * Register client with email and password
     *
     * POST /api/client/register
     *
     * Requires auth.client middleware (already logged in with code)
     */
    public function register(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs bejelentkezve.',
            ], 401);
        }

        // Ellenőrizzük, hogy már regisztrált-e
        if ($client->is_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Már regisztrálva vagy.',
            ], 422);
        }

        // Ellenőrizzük, hogy van-e olyan albumja, ami engedélyezi a regisztrációt
        if (!$client->hasAlbumWithRegistrationAllowed()) {
            return response()->json([
                'success' => false,
                'message' => 'A regisztráció nem engedélyezett.',
            ], 403);
        }

        // Validáció
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                // Email egyediség ellenőrzés: ne legyen már regisztrált kliens ezzel az email-lel
                function ($attribute, $value, $fail) use ($client) {
                    $exists = PartnerClient::registered()
                        ->byEmail($value)
                        ->where('id', '!=', $client->id)
                        ->exists();

                    if ($exists) {
                        $fail('Ez az email cím már használatban van.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ], [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'password.required' => 'A jelszó megadása kötelező.',
            'password.confirmed' => 'A jelszavak nem egyeznek.',
            'password.min' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.',
        ]);

        // Email frissítése (ha módosult)
        if ($client->email !== $validated['email']) {
            $client->email = $validated['email'];
        }

        // Regisztráció végrehajtása
        $client->register($validated['password']);

        // Új token generálás (a régi kód-alapú token érvénytelen)
        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        // Régi tokenek törlése
        DB::table('personal_access_tokens')
            ->where('partner_client_id', $client->id)
            ->delete();

        // Új token létrehozása
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
     *
     * POST /api/client/login
     *
     * Public endpoint (no auth required)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'password.required' => 'A jelszó megadása kötelező.',
        ]);

        // Kliens keresése email alapján
        $client = PartnerClient::registered()
            ->byEmail($validated['email'])
            ->with('partner')
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Hibás email vagy jelszó.',
            ], 401);
        }

        // Jelszó ellenőrzés
        if (!Hash::check($validated['password'], $client->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Hibás email vagy jelszó.',
            ], 401);
        }

        // Partner feature ellenőrzés
        if (!$client->partner || !$client->partner->hasFeature('client_orders')) {
            return response()->json([
                'success' => false,
                'message' => 'A funkció nem elérhető.',
            ], 403);
        }

        // Bejelentkezés rögzítése
        $client->recordLogin();

        // Token generálás
        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        // Régi tokenek törlése (opcionális - több eszközön is bejelentkezhet)
        // DB::table('personal_access_tokens')
        //     ->where('partner_client_id', $client->id)
        //     ->delete();

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

        // Albumok lekérése
        $albums = $client->albums()
            ->where('status', '!=', 'draft')
            ->latest()
            ->get()
            ->map(fn ($album) => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
                'photosCount' => $album->photos_count,
                'maxSelections' => $album->max_selections,
                'minSelections' => $album->min_selections,
                'isCompleted' => $album->isCompleted(),
                'canDownload' => $album->canDownload(),
            ]);

        return response()->json([
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
        ]);
    }

    /**
     * Get current client profile
     *
     * GET /api/client/profile
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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'isRegistered' => $client->is_registered,
                'registeredAt' => $client->registered_at?->toIso8601String(),
                'wantsNotifications' => $client->wants_notifications,
                'canRegister' => !$client->is_registered && $client->hasAlbumWithRegistrationAllowed(),
            ],
        ]);
    }

    /**
     * Update notification preferences
     *
     * PATCH /api/client/notifications
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
     *
     * POST /api/client/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        if (!$client || !$client->is_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Csak regisztrált ügyfelek változtathatják meg a jelszavukat.',
            ], 403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ], [
            'current_password.required' => 'A jelenlegi jelszó megadása kötelező.',
            'password.required' => 'Az új jelszó megadása kötelező.',
            'password.confirmed' => 'A jelszavak nem egyeznek.',
        ]);

        // Jelenlegi jelszó ellenőrzés
        if (!Hash::check($validated['current_password'], $client->password)) {
            return response()->json([
                'success' => false,
                'message' => 'A jelenlegi jelszó hibás.',
            ], 422);
        }

        $client->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jelszó sikeresen megváltoztatva.',
        ]);
    }

    /**
     * Check if album can be downloaded
     */
    private function canDownloadAlbum($album): bool
    {
        if (!$album->isCompleted() || !$album->finalized_at) {
            return false;
        }

        // Ha nincs időkorlát, mindig letölthető
        if (!$album->download_days) {
            return true;
        }

        // Ellenőrizzük, hogy a letöltési idő nem járt-e le
        $expiresAt = $album->finalized_at->addDays($album->download_days);

        return now()->lt($expiresAt);
    }
}
