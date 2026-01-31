<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'shipping_method_id',
        'weight_from_grams',
        'weight_to_grams',
        'price_huf',
        'is_express',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'weight_from_grams' => 'integer',
            'weight_to_grams' => 'integer',
            'price_huf' => 'integer',
            'is_express' => 'boolean',
        ];
    }

    /**
     * Shipping method relationship
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Check if weight is within this rate's range
     */
    public function coversWeight(int $weightGrams): bool
    {
        if ($weightGrams < $this->weight_from_grams) {
            return false;
        }

        if ($this->weight_to_grams !== null && $weightGrams > $this->weight_to_grams) {
            return false;
        }

        return true;
    }

    /**
     * Get weight range as human-readable string
     */
    public function getWeightRangeAttribute(): string
    {
        $from = $this->weight_from_grams;
        $to = $this->weight_to_grams;

        if ($to === null) {
            return "{$from}g+";
        }

        return "{$from}g - {$to}g";
    }
}
