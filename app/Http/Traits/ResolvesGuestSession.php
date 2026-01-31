<?php

namespace App\Http\Traits;

use App\Models\TabloGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ResolvesGuestSession Trait
 *
 * Guest session kezelés közös logikája.
 * Kiszervezi a duplikált session keresést a controllerekből.
 */
trait ResolvesGuestSession
{
    /**
     * Resolve guest session from request header (required).
     *
     * @return TabloGuestSession|JsonResponse Returns session or error response
     */
    protected function resolveGuestSession(Request $request, int $projectId): TabloGuestSession|JsonResponse
    {
        $guestSessionToken = $request->header('X-Guest-Session');

        if (! $guestSessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Hiányzó session azonosító.',
            ], 401);
        }

        $guestSession = TabloGuestSession::findByTokenAndProject($guestSessionToken, $projectId);

        if (! $guestSession) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen session.',
            ], 401);
        }

        return $guestSession;
    }

    /**
     * Resolve guest session from request header (optional).
     *
     * @return TabloGuestSession|null Returns session or null if not found
     */
    protected function resolveOptionalGuestSession(Request $request, int $projectId): ?TabloGuestSession
    {
        $guestSessionToken = $request->header('X-Guest-Session');

        if (! $guestSessionToken) {
            return null;
        }

        return TabloGuestSession::findByTokenAndProject($guestSessionToken, $projectId);
    }

    /**
     * Check if guest session is banned.
     *
     * @return JsonResponse|null Returns error response if banned, null otherwise
     */
    protected function checkGuestBanned(TabloGuestSession $guestSession): ?JsonResponse
    {
        if ($guestSession->is_banned) {
            return response()->json([
                'success' => false,
                'message' => 'A szavazás nem engedélyezett.',
            ], 403);
        }

        return null;
    }
}
