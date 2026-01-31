<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'work_session_id',
        'package_id',
        'coupon_id',
        'coupon_discount',
        'subtotal_huf',
        'discount_huf',
        'total_gross_huf',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_address',
        'company_name',
        'tax_number',
        'billing_address',
        'is_company_purchase',
        'payment_method_id',
        'shipping_method_id',
        'package_point_id',
        'shipping_address',
        'shipping_cost_huf',
        'cod_fee_huf',
        'status',
        'stripe_pi',
        'invoice_no',
        'invoice_issued_at',
    ];

    /**
     * Hidden attributes (sensitive data)
     */
    protected $hidden = [
        'stripe_pi',
        'guest_address',
        'billing_address',
        'shipping_address',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'subtotal_huf' => 'integer',
            'discount_huf' => 'integer',
            'total_gross_huf' => 'integer',
            'coupon_discount' => 'integer',
            'shipping_cost_huf' => 'integer',
            'cod_fee_huf' => 'integer',
            'is_company_purchase' => 'boolean',
            'invoice_issued_at' => 'datetime',
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
     * Order items relationship
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Payment method relationship
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Shipping method relationship
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Package point relationship
     */
    public function packagePoint(): BelongsTo
    {
        return $this->belongsTo(PackagePoint::class);
    }

    /**
     * Scope for user orders
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for guest orders
     */
    public function scopeForGuest($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if order is guest order
     */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Check if order is paid
     */
    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'completed']);
    }

    /**
     * Get total with discount applied
     */
    public function getTotalWithDiscount(): int
    {
        return max(0, $this->subtotal_huf - $this->discount_huf);
    }

    /**
     * Get customer name (user or guest)
     */
    public function getCustomerName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Unknown';
    }

    /**
     * Get customer email (user or guest)
     */
    public function getCustomerEmail(): string
    {
        return $this->user?->email ?? $this->guest_email ?? '';
    }

    /**
     * Calculate total weight of order items in grams
     *
     * @return int Total weight in grams
     */
    public function calculateTotalWeight(): int
    {
        return $this->items->sum(function ($item) {
            $printSize = PrintSize::find($item->size);
            if (! $printSize || ! $printSize->weight_grams) {
                return 0;
            }

            return $printSize->weight_grams * $item->quantity;
        });
    }

    /**
     * Get grand total including shipping and COD fees
     *
     * @return int Total in HUF
     */
    public function getGrandTotal(): int
    {
        return $this->total_gross_huf + $this->shipping_cost_huf + $this->cod_fee_huf;
    }

    /**
     * Boot the model and register event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = $order->generateOrderNumber();
            }
        });
    }

    /**
     * Generate unique order number
     * Format: R{YYYYMMDD}{XXXX} (e.g., R202510151234)
     *
     * @return string Unique order number
     */
    public function generateOrderNumber(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $date = now()->format('Ymd');
            $random = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $orderNumber = "R{$date}{$random}";

            $exists = static::where('order_number', $orderNumber)->exists();
            $attempt++;

            if (! $exists) {
                return $orderNumber;
            }
        } while ($attempt < $maxAttempts);

        // Fallback: add microseconds if all attempts failed
        $microseconds = substr((string) microtime(true), -4);

        return "R{$date}{$microseconds}";
    }
}
