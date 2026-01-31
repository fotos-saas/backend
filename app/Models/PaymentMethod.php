<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'is_active',
        'sort_order',
        'description',
        'icon',
        'bank_account_number',
        'account_holder_name',
        'bank_name',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Orders relationship
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope for active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Check if payment method is cash (COD)
     */
    public function isCash(): bool
    {
        return $this->type === 'cash';
    }
}
