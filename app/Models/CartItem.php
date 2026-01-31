<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'photo_id',
        'print_size_id',
        'qty',
        'type',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    /**
     * Cart relationship
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Photo relationship
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * Print size relationship
     */
    public function printSize(): BelongsTo
    {
        return $this->belongsTo(PrintSize::class);
    }

    /**
     * Get subtotal for this cart item
     */
    public function getSubtotal(): int
    {
        if ($this->type === 'print' && $this->print_size_id) {
            $price = Price::whereHas('priceList', function ($query) {
                $query->where('active', true);
            })
                ->where('print_size_id', $this->print_size_id)
                ->first();

            if ($price) {
                return $price->gross_huf * $this->qty;
            }
        }

        return 0;
    }
}
