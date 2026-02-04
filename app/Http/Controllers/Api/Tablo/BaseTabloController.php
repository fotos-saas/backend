<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApiResponseTrait;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base Tablo Controller
 *
 * Alap osztály minden Tablo API controllerhez.
 * Közös funkcionalitások:
 * - JSON response formázás (ApiResponseTrait)
 * - Projekt azonosítás token alapján
 * - Guest session kezelés
 */
abstract class BaseTabloController extends Controller
{
    use ApiResponseTrait;

    // ============================================================================
    // PROJECT & TOKEN HELPERS
    // ============================================================================

    /**
     * Projekt ID lekérése a tokenből.
     */
    protected function getProjectId(Request $request): int
    {
        return $request->user()->currentAccessToken()->tablo_project_id;
    }

    /**
     * Projekt lekérése a tokenből.
     * Null-t ad vissza, ha nem található.
     */
    protected function getProject(Request $request): ?TabloProject
    {
        $projectId = $this->getProjectId($request);

        return TabloProject::find($projectId);
    }

    /**
     * Projekt lekérése vagy 404 hiba.
     * JsonResponse-t ad vissza hiba esetén.
     */
    protected function getProjectOrFail(Request $request): TabloProject|JsonResponse
    {
        $project = $this->getProject($request);

        if (! $project) {
            return $this->notFoundResponse('Projekt nem található');
        }

        return $project;
    }

    /**
     * Access token lekérése.
     */
    protected function getAccessToken(Request $request): mixed
    {
        return $request->user()->currentAccessToken();
    }

    /**
     * Kapcsolattartó-e a felhasználó (code token).
     * A token name mezője alapján döntünk: 'tablo-auth-token' = contact
     */
    protected function isContact(Request $request): bool
    {
        $token = $this->getAccessToken($request);
        return $token && $token->name === 'tablo-auth-token';
    }

    /**
     * Contact ID lekérése a tokenből.
     */
    protected function getContactId(Request $request): ?int
    {
        return $this->getAccessToken($request)->contact_id;
    }

    // ============================================================================
    // GUEST SESSION HELPERS
    // ============================================================================

    /**
     * Guest session token lekérése a headerből.
     */
    protected function getGuestSessionToken(Request $request): ?string
    {
        return $request->header('X-Guest-Session');
    }

    /**
     * Guest session lekérése a headerből.
     * Null-t ad vissza, ha nincs vagy érvénytelen.
     */
    protected function getGuestSession(Request $request): ?TabloGuestSession
    {
        $token = $this->getGuestSessionToken($request);

        if (! $token) {
            return null;
        }

        $projectId = $this->getProjectId($request);

        return TabloGuestSession::findByTokenAndProject($token, $projectId);
    }

    /**
     * Guest session lekérése vagy 401 hiba.
     */
    protected function getGuestSessionOrFail(Request $request): TabloGuestSession|JsonResponse
    {
        $token = $this->getGuestSessionToken($request);

        if (! $token) {
            return $this->unauthorizedResponse('Hiányzó session azonosító.');
        }

        $guestSession = $this->getGuestSession($request);

        if (! $guestSession) {
            return $this->unauthorizedResponse('Érvénytelen session.');
        }

        return $guestSession;
    }

    /**
     * Ellenőrzi, hogy a guest session aktív-e (nem banned).
     */
    protected function isGuestActive(TabloGuestSession $guestSession): bool
    {
        return ! $guestSession->is_banned;
    }

    /**
     * Guest session ellenőrzése + ban check + verifikáció check.
     * Hibát ad vissza, ha nincs session, banned, vagy nincs verifikálva.
     */
    protected function requireActiveGuestSession(Request $request): TabloGuestSession|JsonResponse
    {
        $result = $this->getGuestSessionOrFail($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if (! $this->isGuestActive($result)) {
            return $this->forbiddenResponse('A művelet nem engedélyezett.');
        }

        // Verifikáció ellenőrzés - csak verified státuszú session használhat API-t
        if (! $result->isVerified()) {
            return $this->forbiddenResponse('A session nincs verifikálva.');
        }

        return $result;
    }

    /**
     * Guest session ellenőrzése + ban check, verifikáció NÉLKÜL.
     * Használható olyan endpointokhoz, ahol a pending státuszú user is hozzáférhet.
     */
    protected function requireActiveGuestSessionWithoutVerification(Request $request): TabloGuestSession|JsonResponse
    {
        $result = $this->getGuestSessionOrFail($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if (! $this->isGuestActive($result)) {
            return $this->forbiddenResponse('A művelet nem engedélyezett.');
        }

        return $result;
    }
}
