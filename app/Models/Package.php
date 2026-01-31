<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'album_id',
        'price',
        'selectable_photos_count',
        'coupon_policy',
        'allowed_coupon_ids',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'selectable_photos_count' => 'integer',
            'allowed_coupon_ids' => 'array',
        ];
    }

    /**
     * Album relationship (optional)
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Package items relationship
     */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    /**
     * Check if coupon is allowed for this package
     */
    public function isCouponAllowed(Coupon $coupon): bool
    {
        return match ($this->coupon_policy) {
            'all' => true,
            'none' => false,
            'specific' => in_array($coupon->id, $this->allowed_coupon_ids ?? []),
            default => true,
        };
    }
}
