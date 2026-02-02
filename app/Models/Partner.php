<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'tax_number',
        'billing_country',
        'billing_postal_code',
        'billing_city',
        'billing_address',
        'phone',
        'plan',
        'billing_cycle',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_started_at',
        'subscription_ends_at',
        'paused_at',
        'storage_limit_gb',
        'max_classes',
        'features',
        'deletion_scheduled_at',
    ];

    protected $casts = [
        'subscription_started_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'features' => 'array',
        'storage_limit_gb' => 'integer',
        'max_classes' => 'integer',
    ];

    /**
     * Plan definitions with limits
     */
    public const PLANS = [
        'alap' => [
            'name' => 'Alap',
            'storage_limit_gb' => 20,
            'max_classes' => 3,
            'features' => ['online_selection', 'templates', 'qr_sharing', 'email_support'],
        ],
        'iskola' => [
            'name' => 'Iskola',
            'storage_limit_gb' => 100,
            'max_classes' => 20,
            'features' => ['online_selection', 'templates', 'qr_sharing', 'subdomain', 'stripe_payments', 'sms_notifications', 'priority_support'],
        ],
        'studio' => [
            'name' => 'Stúdió',
            'storage_limit_gb' => 500,
            'max_classes' => null, // Unlimited
            'features' => ['online_selection', 'templates', 'qr_sharing', 'custom_domain', 'white_label', 'api_access', 'dedicated_support', 'stripe_payments', 'sms_notifications'],
        ],
    ];

    /**
     * Get the user that owns the partner account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the active subscription discount for this partner.
     */
    public function activeDiscount(): HasOne
    {
        return $this->hasOne(SubscriptionDiscount::class)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->latest();
    }

    /**
     * Check if subscription is active
     */
    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }

        if ($this->subscription_ends_at && $this->subscription_ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if partner has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        $planFeatures = self::PLANS[$this->plan]['features'] ?? [];

        return in_array($feature, $planFeatures) || in_array($feature, $this->features ?? []);
    }

    /**
     * Get storage limit in bytes
     */
    public function getStorageLimitBytes(): int
    {
        $limitGb = $this->storage_limit_gb ?? self::PLANS[$this->plan]['storage_limit_gb'] ?? 20;

        return $limitGb * 1024 * 1024 * 1024;
    }

    /**
     * Get max classes limit (null = unlimited)
     */
    public function getMaxClasses(): ?int
    {
        return $this->max_classes ?? self::PLANS[$this->plan]['max_classes'] ?? 3;
    }

    /**
     * Apply plan limits when setting plan
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($partner) {
            if ($partner->plan && isset(self::PLANS[$partner->plan])) {
                $planConfig = self::PLANS[$partner->plan];
                $partner->storage_limit_gb = $partner->storage_limit_gb ?? $planConfig['storage_limit_gb'];
                $partner->max_classes = $partner->max_classes ?? $planConfig['max_classes'];
                $partner->features = $partner->features ?? $planConfig['features'];
            }
        });
    }
}
