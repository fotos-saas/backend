<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PartnerAddon Model
 *
 * Partner addon előfizetések (pl. Közösségi csomag)
 */
class PartnerAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'addon_key',
        'stripe_subscription_item_id',
        'status',
        'activated_at',
        'canceled_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Get the partner that owns this addon
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Check if addon is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get addon definition from Partner::ADDONS
     */
    public function getDefinition(): ?array
    {
        return Partner::ADDONS[$this->addon_key] ?? null;
    }

    /**
     * Get addon name
     */
    public function getName(): string
    {
        return $this->getDefinition()['name'] ?? $this->addon_key;
    }

    /**
     * Get features included in this addon
     */
    public function getIncludedFeatures(): array
    {
        return $this->getDefinition()['includes'] ?? [];
    }

    /**
     * Scope for active addons
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
