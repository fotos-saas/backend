<?php

namespace App\Http\Middleware;

use App\Enums\TabloProjectStatus;
use App\Models\TabloProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTabloProjectStatus
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user's tablo project is still valid.
     * If not, revoke the token and return 401.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check if user is authenticated via Sanctum
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Get the current access token
        $token = $user->currentAccessToken();
        if (! $token) {
            return $next($request);
        }

        // If token doesn't have tablo_project_id, skip validation
        // (this is for regular user logins, not tablo code guests)
        if (! $token->tablo_project_id) {
            return $next($request);
        }

        // Check if tablo project is still valid
        $tabloProject = TabloProject::find($token->tablo_project_id);

        // If tablo project doesn't exist (deleted) or invalid → revoke token
        if (! $tabloProject || ! $this->isTabloProjectValid($tabloProject)) {
            // Revoke the current token
            $token->delete();

            return response()->json([
                'message' => 'A tablo projekt már nem érvényes.',
                'error' => 'tablo_project_invalid',
            ], 401);
        }

        // Tablo project is valid, continue
        return $next($request);
    }

    /**
     * Check if tablo project is valid for access code authentication
     */
    private function isTabloProjectValid(TabloProject $tabloProject): bool
    {
        // Check if access code is enabled
        if (! $tabloProject->access_code_enabled) {
            return false;
        }

        // Check if not expired
        if ($tabloProject->access_code_expires_at && $tabloProject->access_code_expires_at->isPast()) {
            return false;
        }

        // Check if status is not "done" or "in_print" (these are finished states)
        if (in_array($tabloProject->status, [TabloProjectStatus::Done, TabloProjectStatus::InPrint])) {
            return false;
        }

        return true;
    }
}
