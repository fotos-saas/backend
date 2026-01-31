<?php

namespace App\Models;

use App\Enums\InvoicingProviderType;
use Illuminate\Database\Eloquent\Model;

class InvoicingProvider extends Model
{
    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'provider_type',
        'is_active',
        'api_key',
        'agent_key',
        'api_v3_key',
        'settings',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'provider_type' => InvoicingProviderType::class,
            'is_active' => 'boolean',
            'settings' => 'array',
            'api_key' => 'encrypted',
            'agent_key' => 'encrypted',
            'api_v3_key' => 'encrypted',
        ];
    }

    /**
     * Scope for active providers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get parsed settings configuration
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->settings ?? [];
        }

        return data_get($this->settings, $key);
    }

    /**
     * Check if this provider is Számlázz.hu
     */
    public function isSzamlazzHu(): bool
    {
        return $this->provider_type === InvoicingProviderType::SzamlazzHu;
    }

    /**
     * Check if this provider is Billingo
     */
    public function isBillingo(): bool
    {
        return $this->provider_type === InvoicingProviderType::Billingo;
    }
}
