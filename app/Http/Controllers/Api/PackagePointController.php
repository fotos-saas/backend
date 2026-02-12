<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetPackagePointsRequest;
use App\Http\Requests\Api\SearchNearbyPackagePointsRequest;
use App\Services\PackagePointService;
use Illuminate\Http\JsonResponse;

class PackagePointController extends Controller
{
    public function __construct(
        private PackagePointService $packagePointService
    ) {}

    /**
     * Get package points
     */
    public function index(GetPackagePointsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $provider = $validated['provider'] ?? null;
        $search = $validated['search'] ?? null;

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
    public function searchNearby(SearchNearbyPackagePointsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $radius = (int) ($validated['radius'] ?? 5000);
        $provider = $validated['provider'] ?? null;

        $points = $this->packagePointService->searchNearby($latitude, $longitude, $radius, $provider);

        return response()->json($points);
    }
}
