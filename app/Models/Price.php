<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    protected $fillable = [
        'price_list_id',
        'print_size_id',
        'price',
        'volume_discounts',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'volume_discounts' => 'array',
        ];
    }

    /**
     * Price list relationship
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Print size relationship
     */
    public function printSize(): BelongsTo
    {
        return $this->belongsTo(PrintSize::class);
    }

    /**
     * Calculate price for given quantity with volume discounts
     */
    public function calculatePriceForQuantity(int $quantity): int
    {
        $basePrice = $this->price;
        $discountPercent = 0;

        // Find applicable volume discount
        if ($this->volume_discounts && is_array($this->volume_discounts)) {
            // Sort by minQty descending to find the highest applicable discount
            $discounts = collect($this->volume_discounts)
                ->sortByDesc('minQty');

            foreach ($discounts as $discount) {
                if ($quantity >= ($discount['minQty'] ?? 0)) {
                    $discountPercent = $discount['percentOff'] ?? 0;
                    break;
                }
            }
        }

        // Calculate discounted price
        if ($discountPercent > 0) {
            $discountedPrice = $basePrice * (1 - $discountPercent / 100);

            return (int) round($discountedPrice);
        }

        return $basePrice;
    }

    /**
     * Calculate total for given quantity
     */
    public function calculateTotal(int $quantity): int
    {
        return $this->calculatePriceForQuantity($quantity) * $quantity;
    }
}
