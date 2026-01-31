<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\WorkSession;

class CheckWorkSessionStatus
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user's work session is still valid.
     * If not, revoke the token and return 401.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check if user is authenticated via Sanctum
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Get the current access token
        $token = $user->currentAccessToken();
        if (!$token) {
            return $next($request);
        }

        // If token doesn't have work_session_id, skip validation
        // (this is for regular user logins, not digit code guests)
        if (!$token->work_session_id) {
            return $next($request);
        }

        // Check if work session is still valid
        $workSession = WorkSession::find($token->work_session_id);

        // If work session doesn't exist (deleted) or invalid → revoke token
        if (!$workSession || !$this->isWorkSessionValid($workSession)) {
            // Revoke the current token
            $token->delete();

            return response()->json([
                'message' => 'A munkamenet már nem érvényes.',
                'error' => 'work_session_invalid',
            ], 401);
        }

        // Work session is valid, continue
        return $next($request);
    }

    /**
     * Check if work session is valid for digit code authentication
     */
    private function isWorkSessionValid(WorkSession $workSession): bool
    {
        // Check if digit code is enabled
        if (!$workSession->digit_code_enabled) {
            return false;
        }

        // Check if not expired
        if ($workSession->digit_code_expires_at && $workSession->digit_code_expires_at->isPast()) {
            return false;
        }

        // Check if status is active (only active sessions are valid)
        if ($workSession->status !== 'active') {
            return false;
        }

        return true;
    }
}
