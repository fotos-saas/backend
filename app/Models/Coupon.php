<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'enabled',
        'expires_at',
        'min_order_value',
        'allowed_emails',
        'allowed_album_ids',
        'allowed_sizes',
        'max_usage',
        'usage_count',
        'first_order_only',
        'auto_apply',
        'stackable',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'enabled' => 'boolean',
        'expires_at' => 'datetime',
        'allowed_emails' => 'array',
        'allowed_album_ids' => 'array',
        'allowed_sizes' => 'array',
        'min_order_value' => 'integer',
        'max_usage' => 'integer',
        'usage_count' => 'integer',
        'first_order_only' => 'boolean',
        'auto_apply' => 'boolean',
        'stackable' => 'boolean',
    ];

    /**
     * Scope for valid coupons (enabled and not expired)
     */
    public function scopeValid($query)
    {
        return $query->where('enabled', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_usage')
                    ->orWhereColumn('usage_count', '<', 'max_usage');
            });
    }

    /**
     * Check if coupon is valid for order
     */
    public function isValidForOrder(int $orderTotal, ?User $user = null, ?int $albumId = null, bool $isPackageMode = false): bool
    {
        // Check if enabled
        if (! $this->enabled) {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check usage limit
        if ($this->max_usage && $this->usage_count >= $this->max_usage) {
            return false;
        }

        // Check minimum order value (skip for package mode)
        if (! $isPackageMode && $this->min_order_value && $orderTotal < $this->min_order_value) {
            return false;
        }

        // Check allowed emails
        if ($user && $this->allowed_emails && count($this->allowed_emails) > 0) {
            if (! in_array($user->email, $this->allowed_emails)) {
                return false;
            }
        }

        // Check allowed albums
        if ($albumId && $this->allowed_album_ids && count($this->allowed_album_ids) > 0) {
            if (! in_array($albumId, $this->allowed_album_ids)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount(int $orderTotal): int
    {
        if ($this->type === 'percent') {
            return (int) round(($orderTotal * $this->value) / 100);
        }

        return (int) $this->value;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Check if coupon is valid for package
     */
    public function isValidForPackage(Package $package): bool
    {
        return $package->isCouponAllowed($this);
    }

    /**
     * Check if coupon is valid for work session
     */
    public function isValidForWorkSession(WorkSession $workSession): bool
    {
        return $workSession->isCouponAllowed($this);
    }

    /**
     * Check if coupon is valid in context (WorkSession has priority over Package)
     */
    public function isValidInContext(?Package $package = null, ?WorkSession $workSession = null): bool
    {
        // WorkSession has priority
        if ($workSession) {
            return $this->isValidForWorkSession($workSession);
        }

        // Package is second priority
        if ($package) {
            return $this->isValidForPackage($package);
        }

        // No context = valid
        return true;
    }
}
