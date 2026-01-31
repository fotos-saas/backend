<?php

namespace App\Http\Middleware;

use App\Models\PartnerClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate PartnerClient via Bearer token.
 *
 * Token is stored in personal_access_tokens table with partner_client_id field.
 * Client tokens are created during loginAsPartnerClient in AuthController.
 */
class AuthenticateClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs érvényes token.',
            ], 401);
        }

        // Find token in database (hashed)
        $hashedToken = hash('sha256', $token);
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->whereNotNull('partner_client_id')
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen vagy lejárt token.',
            ], 401);
        }

        // Find client
        $client = PartnerClient::with('partner')->find($tokenRecord->partner_client_id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Az ügyfél nem található.',
            ], 404);
        }

        // Check if partner has client_orders feature enabled
        if (!$client->partner || !$client->partner->hasFeature('client_orders')) {
            return response()->json([
                'success' => false,
                'message' => 'A funkció nem elérhető.',
            ], 403);
        }

        // Attach client to request for later use
        $request->attributes->set('client', $client);
        $request->attributes->set('partner_client_id', $client->id);
        $request->attributes->set('partner_id', $client->tablo_partner_id);

        return $next($request);
    }
}
