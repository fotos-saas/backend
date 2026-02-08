<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSetting extends Model
{
    protected $fillable = [
        'tablo_partner_id',
        'is_enabled',
        'welcome_message',
        'min_order_amount_huf',
        'shipping_cost_huf',
        'shipping_free_threshold_huf',
        'allow_pickup',
        'allow_shipping',
        'terms_text',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'min_order_amount_huf' => 'integer',
        'shipping_cost_huf' => 'integer',
        'shipping_free_threshold_huf' => 'integer',
        'allow_pickup' => 'boolean',
        'allow_shipping' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }
}
