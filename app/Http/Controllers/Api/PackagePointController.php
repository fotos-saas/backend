<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PackagePointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackagePointController extends Controller
{
    public function __construct(
        private PackagePointService $packagePointService
    ) {}

    /**
     * Get package points
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'nullable|in:foxpost,packeta',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider = $request->input('provider');
        $search = $request->input('search');

        if ($search) {
            $points = $this->packagePointService->searchByCityOrZip($search, $provider);
        } elseif ($provider) {
            $points = $this->packagePointService->getActiveByProvider($provider);
        } else {
            $points = collect();
        }

        return response()->json($points);
    }

    /**
     * Search package points near given coordinates
     */
    public function searchNearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:100|max:50000',
            'provider' => 'nullable|in:foxpost,packeta',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        $radius = (int) $request->input('radius', 5000);
        $provider = $request->input('provider');

        $points = $this->packagePointService->searchNearby($latitude, $longitude, $radius, $provider);

        return response()->json($points);
    }
}
