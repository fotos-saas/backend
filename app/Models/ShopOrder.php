<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ShopOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_PICKUP = 'pickup';
    public const DELIVERY_SHIPPING = 'shipping';

    protected $fillable = [
        'order_number',
        'tablo_partner_id',
        'partner_client_id',
        'tablo_guest_session_id',
        'partner_album_id',
        'tablo_gallery_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal_huf',
        'shipping_cost_huf',
        'total_huf',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
        'delivery_method',
        'shipping_address',
        'shipping_notes',
        'shipped_at',
        'tracking_number',
        'customer_notes',
        'internal_notes',
    ];

    protected $casts = [
        'subtotal_huf' => 'integer',
        'shipping_cost_huf' => 'integer',
        'total_huf' => 'integer',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'tablo_guest_session_id');
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(PartnerAlbum::class, 'partner_album_id');
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(TabloGallery::class, 'tablo_gallery_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    // Scopes

    public function scopeByPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('tablo_partner_id', $partnerId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_PENDING, self::STATUS_CANCELLED]);
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getItemsCount(): int
    {
        return $this->items->sum('quantity');
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $lastOrder = static::where('order_number', 'like', "WS{$date}-%")
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->first();

        $sequence = 1;
        if ($lastOrder) {
            $lastSequence = (int) substr($lastOrder->order_number, -4);
            $sequence = $lastSequence + 1;
        }

        return sprintf('WS%s-%04d', $date, $sequence);
    }
}
