<?php

namespace App\Http\Controllers\Api;

use App\Constants\TokenNames;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InviteRegisterRequest;
use App\Http\Requests\Api\ValidateInviteCodeRequest;
use App\Models\PartnerInvitation;
use App\Models\User;
use App\Services\PartnerInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Invite Register Controller
 *
 * Meghívó kóddal történő regisztráció.
 */
class InviteRegisterController extends Controller
{
    public function __construct(
        private readonly PartnerInvitationService $invitationService
    ) {}

    /**
     * Meghívó kód validálása
     *
     * POST /api/invite/validate
     */
    public function validateCode(ValidateInviteCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $invitation = $this->invitationService->validateCode($validated['code']);

        if (! $invitation) {
            return response()->json([
                'valid' => false,
                'message' => 'Érvénytelen vagy lejárt meghívó kód.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'roleName' => $invitation->role_name,
                'partnerName' => $invitation->partner->name,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Regisztráció meghívó kóddal
     *
     * POST /api/invite/register
     */
    public function register(InviteRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Meghívó validálása
        $invitation = $this->invitationService->validateCode($validated['code']);

        if (! $invitation) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt meghívó kód.',
            ], 404);
        }

        // Email ellenőrzés (a meghívón szereplő email-nek meg kell egyeznie)
        if (strtolower($validated['email']) !== strtolower($invitation->email)) {
            return response()->json([
                'message' => 'Az email cím nem egyezik a meghívóban szereplő címmel.',
            ], 422);
        }

        // Létező user ellenőrzése
        $existingUser = User::where('email', $validated['email'])->first();

        try {
            $user = DB::transaction(function () use ($validated, $invitation, $existingUser) {
                if ($existingUser) {
                    // Ha már van ilyen email-lel user, meghívó elfogadása
                    $this->invitationService->acceptInvitation($invitation, $existingUser);
                    return $existingUser;
                }

                // Új user létrehozása
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => strtolower($validated['email']),
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'password_set' => true,
                    'email_verified_at' => now(), // Meghívóval regisztrálókat hitelesítettnek tekintjük
                ]);

                // Meghívó elfogadása
                $this->invitationService->acceptInvitation($invitation, $user);

                return $user;
            });

            // Token generálása
            $token = $user->createToken(TokenNames::PARTNER_AUTH)->plainTextToken;

            return response()->json([
                'message' => 'Sikeres regisztráció!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'partner' => [
                    'id' => $invitation->partner->id,
                    'name' => $invitation->partner->name,
                ],
                'role' => $invitation->role,
                'roleName' => $invitation->role_name,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Hiba történt a regisztráció során.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Meglévő felhasználó meghívó elfogadása (bejelentkezve)
     *
     * POST /api/invite/accept
     */
    public function accept(ValidateInviteCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Bejelentkezés szükséges.',
            ], 401);
        }

        // Meghívó validálása
        $invitation = $this->invitationService->validateCode($validated['code']);

        if (! $invitation) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt meghívó kód.',
            ], 404);
        }

        // Email ellenőrzés
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return response()->json([
                'message' => 'A meghívó egy másik email címre szól.',
            ], 422);
        }

        try {
            $this->invitationService->acceptInvitation($invitation, $user);

            return response()->json([
                'message' => 'Meghívó elfogadva!',
                'partner' => [
                    'id' => $invitation->partner->id,
                    'name' => $invitation->partner->name,
                ],
                'role' => $invitation->role,
                'roleName' => $invitation->role_name,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Hiba történt a meghívó elfogadása során.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
