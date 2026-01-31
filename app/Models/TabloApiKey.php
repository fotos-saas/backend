<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TabloApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Generate a new API key
     */
    public static function generateKey(): string
    {
        return Str::random(64);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope active keys
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find key by value
     */
    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)->active()->first();
    }
}
