<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if partner has a specific feature enabled.
 *
 * Usage in routes:
 * Route::middleware('partner.feature:client_orders')->group(...)
 */
class CheckPartnerFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs bejelentkezve.',
            ], 401);
        }

        $partnerId = $user->tablo_partner_id;

        if (!$partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs partnerhez rendelve.',
            ], 403);
        }

        $partner = \App\Models\TabloPartner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'A partner nem található.',
            ], 404);
        }

        if (!$partner->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a funkció nincs engedélyezve a partnered számára.',
            ], 403);
        }

        return $next($request);
    }
}
