<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Map Configuration Controller
 *
 * Provides map provider configuration to frontend
 */
class MapConfigController extends Controller
{
    /**
     * Get map configuration
     *
     * Returns whether Google Maps is enabled
     * Note: Does not send the API key itself for security
     */
    public function getConfig(): JsonResponse
    {
        $apiKey = Setting::get('google_maps_api_key');
        $hasGoogleMaps = ! empty($apiKey);

        return response()->json([
            'provider' => $hasGoogleMaps ? 'google' : 'openstreetmap',
            'has_google_maps' => $hasGoogleMaps,
            // Send API key only if configured (needed for Google Maps script loading)
            'google_maps_api_key' => $hasGoogleMaps ? $apiKey : null,
        ]);
    }
}
