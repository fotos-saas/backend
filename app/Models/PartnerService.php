<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerService extends Model
{
    public const SERVICE_TYPES = [
        'photo_change',
        'extra_retouch',
        'late_fee',
        'rush_fee',
        'additional_copy',
        'custom',
    ];

    public const SERVICE_LABELS = [
        'photo_change' => 'Képcsere',
        'extra_retouch' => 'Extra retusálás',
        'late_fee' => 'Késedelmi díj',
        'rush_fee' => 'Sürgősségi díj',
        'additional_copy' => 'Plusz példány',
        'custom' => 'Egyedi',
    ];

    public const DEFAULT_SERVICES = [
        ['name' => 'Képcsere', 'service_type' => 'photo_change', 'default_price' => 2000, 'sort_order' => 1],
        ['name' => 'Extra retusálás', 'service_type' => 'extra_retouch', 'default_price' => 3000, 'sort_order' => 2],
        ['name' => 'Késedelmi díj', 'service_type' => 'late_fee', 'default_price' => 1500, 'sort_order' => 3],
        ['name' => 'Sürgősségi díj', 'service_type' => 'rush_fee', 'default_price' => 5000, 'sort_order' => 4],
        ['name' => 'Plusz példány', 'service_type' => 'additional_copy', 'default_price' => 4000, 'sort_order' => 5],
        ['name' => 'Egyedi szolgáltatás', 'service_type' => 'custom', 'default_price' => 0, 'sort_order' => 6],
    ];

    protected $fillable = [
        'partner_id',
        'name',
        'description',
        'service_type',
        'default_price',
        'currency',
        'vat_percentage',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'integer',
            'vat_percentage' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ============ Relációk ============

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(GuestBillingCharge::class, 'partner_service_id');
    }

    // ============ Scopes ============

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ============ Helpers ============

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_LABELS[$this->service_type] ?? $this->name;
    }
}
