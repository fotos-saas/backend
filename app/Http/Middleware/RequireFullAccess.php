<?php

namespace App\Http\Middleware;

use App\Constants\TokenNames;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: RequireFullAccess
 *
 * Ellenőrzi, hogy a felhasználó teljes jogosultsággal rendelkezik-e.
 * Csak kódos belépéssel (TokenNames::TABLO_AUTH) rendelkező felhasználók férhetnek hozzá.
 * Share és preview tokennel rendelkezők 403 Forbidden-t kapnak.
 *
 * Használat:
 * - Template kiválasztás/törlés/prioritás
 * - Kapcsolattartó adatok módosítása
 * - Ütemezés frissítése
 */
class RequireFullAccess
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

        // Token típus ellenőrzése - kódos belépés és QR regisztráció engedélyezett
        $tokenName = $token->name;
        $allowedTokens = TokenNames::FULL_ACCESS_TOKENS;

        if (! in_array($tokenName, $allowedTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a művelet csak teljes jogosultsággal érhető el. Megosztott vagy előnézeti linkkel nem lehetséges.',
                'error' => 'insufficient_permissions',
            ], 403);
        }

        return $next($request);
    }
}
