<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ShopOrderItem extends Model
{
    protected $fillable = [
        'shop_order_id',
        'shop_product_id',
        'media_id',
        'paper_size_name',
        'paper_type_name',
        'unit_price_huf',
        'quantity',
        'subtotal_huf',
    ];

    protected $casts = [
        'unit_price_huf' => 'integer',
        'quantity' => 'integer',
        'subtotal_huf' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
