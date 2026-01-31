<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base Tablo Controller
 *
 * Alap osztály minden Tablo API controllerhez.
 * Közös funkcionalitások:
 * - JSON response formázás (success, error, notFound)
 * - Projekt azonosítás token alapján
 * - Guest session kezelés
 */
abstract class BaseTabloController extends Controller
{
    // ============================================================================
    // JSON RESPONSE HELPERS
    // ============================================================================

    /**
     * Sikeres válasz egységes formátumban.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Sikeres',
        int $code = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Hiba válasz egységes formátumban.
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * 404 Not Found válasz.
     */
    protected function notFoundResponse(string $message = 'Nem található'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * 401 Unauthorized válasz.
     */
    protected function unauthorizedResponse(string $message = 'Azonosítás szükséges'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * 403 Forbidden válasz.
     */
    protected function forbiddenResponse(string $message = 'Nincs jogosultság'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * 422 Validation error válasz.
     */
    protected function validationErrorResponse(string $message, array $errors = []): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

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
