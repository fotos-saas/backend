<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagePoint extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'external_id',
        'name',
        'address',
        'city',
        'zip',
        'latitude',
        'longitude',
        'is_active',
        'opening_hours',
        'last_synced_at',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Orders relationship
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope for active package points
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for filtering by provider
     *
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for filtering by city
     *
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeByCity($query, string $city)
    {
        return $query->where('city', 'LIKE', "%{$city}%");
    }

    /**
     * Scope for filtering by zip
     *
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeByZip($query, string $zip)
    {
        return $query->where('zip', 'LIKE', "%{$zip}%");
    }

    /**
     * Calculate distance from given coordinates (in meters)
     * Using Haversine formula
     *
     * @return float Distance in meters
     */
    public function distanceFrom(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad((float) $this->latitude);
        $lonFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get full address as single string
     */
    public function getFullAddressAttribute(): string
    {
        return "{$this->zip} {$this->city}, {$this->address}";
    }
}
