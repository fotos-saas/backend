<?php

namespace App\Services;

use App\Models\PackagePoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackagePointService
{
    /**
     * Foxpost API endpoint
     */
    private const FOXPOST_API_URL = 'https://cdn.foxpost.hu/foxplus.json';

    /**
     * Packeta API endpoint
     */
    private const PACKETA_API_URL = 'https://pickup-point.api.packeta.com/v5';

    /**
     * Sync Foxpost package points from API
     *
     * @return array Stats about sync operation
     */
    public function syncFoxpostPoints(): array
    {
        try {
            Log::info('Starting Foxpost package points sync');

            // Foxpost API is always available (public endpoint)

            $response = Http::timeout(30)->get(self::FOXPOST_API_URL);

            if (! $response->successful()) {
                Log::error('Foxpost API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'API request failed'];
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('Foxpost API returned invalid data');

                return ['success' => false, 'error' => 'Invalid data format'];
            }

            $created = 0;
            $updated = 0;

            foreach ($data as $point) {
                // Map Foxpost API fields to our schema
                // Use operator_id (new format) if available, otherwise fall back to place_id
                $externalId = $point['operator_id'] ?? $point['place_id'] ?? null;

                if (! $externalId) {
                    continue;
                }

                $packagePoint = PackagePoint::updateOrCreate(
                    [
                        'provider' => 'foxpost',
                        'external_id' => (string) $externalId,
                    ],
                    [
                        'name' => $point['name'] ?? 'Foxpost automata',
                        'address' => $point['street'] ?? '',
                        'city' => $point['city'] ?? '',
                        'zip' => $point['zip'] ?? '',
                        'latitude' => $point['geolat'] ?? 0,
                        'longitude' => $point['geolng'] ?? 0,
                        'is_active' => true,
                        'opening_hours' => json_encode($point['open'] ?? null),
                        'last_synced_at' => now(),
                    ]
                );

                if ($packagePoint->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            Log::info('Foxpost sync completed', [
                'created' => $created,
                'updated' => $updated,
            ]);

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
            ];
        } catch (\Exception $e) {
            Log::error('Foxpost sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync Packeta package points from API
     * Note: Requires API key configuration
     *
     * @return array Stats about sync operation
     */
    public function syncPacketaPoints(): array
    {
        try {
            Log::info('Starting Packeta package points sync');

            // Try to get API key from config
            $apiKey = config('services.packeta.api_key');

            if (! $apiKey) {
                Log::warning('Packeta API key not configured');

                return [
                    'success' => false,
                    'error' => 'API kulcs nincs beállítva. Kérjük állítsd be a Szolgáltatói beállításokban.',
                ];
            }

            // Packeta API call with API key in URL
            $response = Http::timeout(30)
                ->get(self::PACKETA_API_URL."/{$apiKey}/branch/json", [
                    'lang' => 'hu',
                ]);

            if (! $response->successful()) {
                Log::error('Packeta API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'API request failed'];
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('Packeta API returned invalid data');

                return ['success' => false, 'error' => 'Invalid data format'];
            }

            $created = 0;
            $updated = 0;

            foreach ($data as $point) {
                $externalId = $point['id'] ?? null;

                if (! $externalId) {
                    continue;
                }

                // Filter for Hungarian points only
                if (($point['country'] ?? '') !== 'hu') {
                    continue;
                }

                // Check if point is active (status.statusId = "1" means active)
                $isActive = ($point['status']['statusId'] ?? null) === '1';

                $packagePoint = PackagePoint::updateOrCreate(
                    [
                        'provider' => 'packeta',
                        'external_id' => (string) $externalId,
                    ],
                    [
                        'name' => $point['name'] ?? 'Packeta csomagpont',
                        'address' => $point['street'] ?? '',
                        'city' => $point['city'] ?? '',
                        'zip' => $point['zip'] ?? '',
                        'latitude' => $point['latitude'] ?? 0,
                        'longitude' => $point['longitude'] ?? 0,
                        'is_active' => $isActive,
                        'opening_hours' => json_encode($point['openingHours'] ?? null),
                        'last_synced_at' => now(),
                    ]
                );

                if ($packagePoint->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            Log::info('Packeta sync completed', [
                'created' => $created,
                'updated' => $updated,
            ]);

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
            ];
        } catch (\Exception $e) {
            Log::error('Packeta sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync Packeta Z-BOX package points (automated lockers).
     */
    public function syncPacketaBoxPoints(): array
    {
        try {
            Log::info('Starting Packeta Z-BOX package points sync');

            // Try to get API key from config
            $apiKey = config('services.packeta.api_key');

            if (! $apiKey) {
                Log::warning('Packeta API key not configured');

                return [
                    'success' => false,
                    'error' => 'API kulcs nincs beállítva. Kérjük állítsd be a Szolgáltatói beállításokban.',
                ];
            }

            // Packeta Z-BOX API call with API key in URL
            $response = Http::timeout(30)
                ->get(self::PACKETA_API_URL."/{$apiKey}/box/json", [
                    'lang' => 'hu',
                ]);

            if (! $response->successful()) {
                Log::error('Packeta Z-BOX API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'API request failed'];
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('Packeta Z-BOX API returned invalid data');

                return ['success' => false, 'error' => 'Invalid data format'];
            }

            $created = 0;
            $updated = 0;

            foreach ($data as $point) {
                $externalId = $point['id'] ?? null;

                if (! $externalId) {
                    continue;
                }

                // Filter for Hungarian Z-BOX points only
                if (($point['country'] ?? '') !== 'hu') {
                    continue;
                }

                // Filter for zbox type only
                if (($point['type'] ?? '') !== 'zbox') {
                    continue;
                }

                // Check if point is active (status.statusId = "1" means active)
                $isActive = ($point['status']['statusId'] ?? null) === '1';

                $packagePoint = PackagePoint::updateOrCreate(
                    [
                        'provider' => 'packeta',
                        'external_id' => (string) $externalId,
                    ],
                    [
                        'name' => $point['name'] ?? 'Packeta Z-BOX automata',
                        'address' => $point['street'] ?? '',
                        'city' => $point['city'] ?? '',
                        'zip' => $point['zip'] ?? '',
                        'latitude' => $point['latitude'] ?? 0,
                        'longitude' => $point['longitude'] ?? 0,
                        'is_active' => $isActive,
                        'opening_hours' => json_encode($point['openingHours'] ?? null),
                        'last_synced_at' => now(),
                    ]
                );

                if ($packagePoint->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            Log::info('Packeta Z-BOX sync completed', [
                'created' => $created,
                'updated' => $updated,
            ]);

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
            ];
        } catch (\Exception $e) {
            Log::error('Packeta Z-BOX sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search package points near given coordinates
     *
     * @param  string|null  $provider  Filter by provider ('foxpost' or 'packeta')
     */
    public function searchNearby(float $latitude, float $longitude, int $radiusMeters = 5000, ?string $provider = null): Collection
    {
        $query = PackagePoint::query()->active();

        if ($provider) {
            $query->where('provider', $provider);
        }

        // Get all points and calculate distance (can be optimized with spatial index)
        $points = $query->get()->map(function ($point) use ($latitude, $longitude) {
            $point->distance = $point->distanceFrom($latitude, $longitude);

            return $point;
        });

        // Filter by radius and sort by distance
        return $points
            ->filter(function ($point) use ($radiusMeters) {
                return $point->distance <= $radiusMeters;
            })
            ->sortBy('distance')
            ->values();
    }

    /**
     * Get active package points by provider
     */
    public function getActiveByProvider(string $provider): Collection
    {
        return PackagePoint::active()
            ->byProvider($provider)
            ->orderBy('city')
            ->orderBy('name')
            ->get();
    }

    /**
     * Search package points by city or zip
     */
    public function searchByCityOrZip(string $search, ?string $provider = null): Collection
    {
        $query = PackagePoint::query()->active();

        if ($provider) {
            $query->where('provider', $provider);
        }

        $query->where(function ($q) use ($search) {
            $q->where('city', 'LIKE', "%{$search}%")
                ->orWhere('zip', 'LIKE', "%{$search}%");
        });

        return $query->orderBy('city')->orderBy('name')->get();
    }
}
