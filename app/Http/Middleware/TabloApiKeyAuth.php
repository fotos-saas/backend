<?php

namespace App\Http\Middleware;

use App\Models\TabloApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TabloApiKeyAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Tablo-Api-Key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API kulcs hiányzik',
            ], 401);
        }

        $key = TabloApiKey::findByKey($apiKey);

        if (! $key) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen vagy inaktív API kulcs',
            ], 401);
        }

        // Update last used timestamp
        $key->markAsUsed();

        // Store the API key in request for potential later use
        $request->attributes->set('tablo_api_key', $key);

        return $next($request);
    }
}
