<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Account Lockout Middleware
 *
 * Checks if the account associated with the email in the request is locked.
 * Returns 423 (Locked) status if the account is locked due to failed login attempts.
 */
class CheckAccountLockout
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');

        if (! $email) {
            return $next($request);
        }

        if ($this->authService->isAccountLocked($email)) {
            $remainingSeconds = $this->authService->getLockoutRemainingSeconds($email);
            $remainingMinutes = ceil($remainingSeconds / 60);

            return response()->json([
                'message' => "A fiók ideiglenesen zárolva van a túl sok sikertelen bejelentkezési kísérlet miatt. Próbáld újra {$remainingMinutes} perc múlva.",
                'locked' => true,
                'locked_until' => now()->addSeconds($remainingSeconds)->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
            ], 423);
        }

        return $next($request);
    }
}
