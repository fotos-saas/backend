<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: CheckFinalizationAccess
 *
 * Ellenőrzi, hogy a felhasználó hozzáférhet-e a véglegesítés funkciókhoz.
 * Csak kódos belépéssel (tablo-auth-token) rendelkező felhasználók férhetnek hozzá.
 * Share és preview tokennel rendelkezők 403 Forbidden-t kapnak.
 */
class CheckFinalizationAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs érvényes session.',
            ], 401);
        }

        // Token típus ellenőrzése
        // Engedélyezett: kódos belépés (tablo-auth-token) és QR regisztráció (qr-registration)
        $tokenName = $token->name;
        $allowedTokens = ['tablo-auth-token', 'qr-registration'];

        if (! in_array($tokenName, $allowedTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'A véglegesítés csak belépési kóddal vagy QR regisztrációval érhető el. Megosztott vagy előnézeti linkkel nem lehetséges.',
            ], 403);
        }

        return $next($request);
    }
}
