<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'work_session_id',
        'package_id',
        'coupon_id',
        'coupon_discount',
        'status',
        'session_token',
        'expires_at',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'coupon_discount' => 'integer',
        ];
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Work session relationship
     */
    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    /**
     * Package relationship
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Coupon relationship
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Cart items relationship
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Scope for active carts (status='draft')
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for expired guest carts
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('session_token')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope for user carts
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for guest session carts
     */
    public function scopeForSession($query, string $token)
    {
        return $query->where('session_token', $token);
    }

    /**
     * Get total items count in cart
     */
    public function getTotalItemsCount(): int
    {
        return $this->items()->sum('qty');
    }

    /**
     * Check if cart is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
