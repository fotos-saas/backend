<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ShopProduct extends Model
{
    protected $fillable = [
        'tablo_partner_id',
        'shop_paper_size_id',
        'shop_paper_type_id',
        'price_huf',
        'is_active',
    ];

    protected $casts = [
        'price_huf' => 'integer',
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    public function paperSize(): BelongsTo
    {
        return $this->belongsTo(ShopPaperSize::class, 'shop_paper_size_id');
    }

    public function paperType(): BelongsTo
    {
        return $this->belongsTo(ShopPaperType::class, 'shop_paper_type_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'shop_product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('tablo_partner_id', $partnerId);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->paperSize->name} - {$this->paperType->name}";
    }
}
