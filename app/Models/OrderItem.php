<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'photo_id',
        'size',
        'quantity',
        'unit_price_huf',
        'total_price_huf',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_huf' => 'integer',
            'total_price_huf' => 'integer',
        ];
    }

    /**
     * Order relationship
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Photo relationship
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
