<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'provider',
        'is_active',
        'is_default',
        'requires_address',
        'requires_parcel_point',
        'supports_cod',
        'cod_fee_huf',
        'min_weight_grams',
        'max_weight_grams',
        'sort_order',
        'description',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'requires_address' => 'boolean',
            'requires_parcel_point' => 'boolean',
            'supports_cod' => 'boolean',
            'cod_fee_huf' => 'integer',
            'min_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Shipping rates relationship
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    /**
     * Orders relationship
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope for active shipping methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Scope for default shipping method
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for filtering by weight
     *
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeForWeight($query, int $weightGrams)
    {
        return $query->where(function ($q) use ($weightGrams) {
            $q->where(function ($subQ) use ($weightGrams) {
                // If min_weight is null or weight >= min_weight
                $subQ->whereNull('min_weight_grams')
                    ->orWhere('min_weight_grams', '<=', $weightGrams);
            })
                ->where(function ($subQ) use ($weightGrams) {
                    // If max_weight is null or weight <= max_weight
                    $subQ->whereNull('max_weight_grams')
                        ->orWhere('max_weight_grams', '>=', $weightGrams);
                });
        });
    }

    /**
     * Get shipping cost for given weight
     *
     * @return int|null Price in HUF or null if no rate found
     */
    public function getCostForWeight(int $weightGrams): ?int
    {
        $rate = $this->rates()
            ->where('weight_from_grams', '<=', $weightGrams)
            ->where(function ($q) use ($weightGrams) {
                $q->whereNull('weight_to_grams')
                    ->orWhere('weight_to_grams', '>=', $weightGrams);
            })
            ->orderBy('weight_from_grams', 'desc')
            ->first();

        return $rate?->price_huf;
    }

    /**
     * Check if this method is available for given weight
     */
    public function isAvailableForWeight(int $weightGrams): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check weight limits
        if ($this->min_weight_grams && $weightGrams < $this->min_weight_grams) {
            return false;
        }

        if ($this->max_weight_grams && $weightGrams > $this->max_weight_grams) {
            return false;
        }

        // Check if there's a rate for this weight
        return $this->getCostForWeight($weightGrams) !== null;
    }
}
