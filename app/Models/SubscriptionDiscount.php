<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionDiscount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'partner_id',
        'percent',
        'valid_until',
        'note',
        'stripe_coupon_id',
        'created_by',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'percent' => 'integer',
    ];

    /**
     * Get the partner that owns the discount.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the admin user who created the discount.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the discount is forever (no expiry).
     */
    public function isForever(): bool
    {
        return $this->valid_until === null;
    }

    /**
     * Check if the discount has expired.
     */
    public function isExpired(): bool
    {
        if ($this->isForever()) {
            return false;
        }

        return $this->valid_until->isPast();
    }

    /**
     * Scope for active (non-expired) discounts.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }
}
