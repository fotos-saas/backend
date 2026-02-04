<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SessionController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Refresh token (return current user)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => $user->role === User::ROLE_GUEST ? 'guest' : 'registered',
                'accessCode' => $user->access_code,
            ],
        ];

        if ($user->role === User::ROLE_GUEST) {
            $workSession = $user->workSessions()->first();
            if ($workSession) {
                $response['user']['workSessionId'] = $workSession->id;
                $response['user']['workSessionName'] = $workSession->name;
            }
        }

        return response()->json($response);
    }

    /**
     * Logout (revoke token and clear user cache)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id;

        if ($userId) {
            try {
                $pattern = config('database.redis.options.prefix') . 'album_photos:*:' . $userId . ':*';
                $keys = Redis::connection()->keys($pattern);

                if (!empty($keys)) {
                    $prefix = config('database.redis.options.prefix');
                    $keysWithoutPrefix = array_map(function($key) use ($prefix) {
                        return str_replace($prefix, '', $key);
                    }, $keys);

                    Cache::deleteMultiple($keysWithoutPrefix);

                    \Log::info('[Auth] User cache cleared on logout', [
                        'user_id' => $userId,
                        'keys_deleted' => count($keys),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('[Auth] Failed to clear cache on logout', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sikeresen kijelentkeztél',
        ]);
    }

    /**
     * Validate current work session status
     */
    public function validateSession(Request $request)
    {
        return response()->json([
            'valid' => true,
            'message' => 'Munkamenet érvényes',
        ]);
    }

    /**
     * Get active sessions for current user
     */
    public function activeSessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $sessions = $this->authService->getActiveSessions($user)
            ->map(function ($session) use ($currentTokenId) {
                $session['is_current'] = $session['id'] === $currentTokenId;

                return $session;
            });

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, int $tokenId)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => 'Nem törölheted a jelenlegi munkamenetedet. Használd a kijelentkezést.',
            ], 400);
        }

        $success = $this->authService->revokeSession($user, $tokenId);

        if (! $success) {
            return response()->json([
                'message' => 'Munkamenet nem található.',
            ], 404);
        }

        return response()->json([
            'message' => 'Munkamenet sikeresen törölve.',
        ]);
    }

    /**
     * Revoke all sessions except current
     */
    public function revokeAllSessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $count = $this->authService->revokeAllSessions($user, $currentTokenId);

        return response()->json([
            'message' => "{$count} munkamenet sikeresen törölve.",
            'revoked_count' => $count,
        ]);
    }
}
