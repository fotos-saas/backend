<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GuestShareToken extends Model
{
    protected $fillable = [
        'token',
        'album_id',
        'email',
        'expires_at',
        'usage_count',
        'max_usage',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'usage_count' => 'integer',
        'max_usage' => 'integer',
    ];

    /**
     * Album relationship
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Guest selections relationship
     */
    public function selections(): HasMany
    {
        return $this->hasMany(GuestSelection::class);
    }

    /**
     * Scope for valid tokens (not expired, not exceeded usage limit)
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereColumn('usage_count', '<', 'max_usage');
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture() && $this->usage_count < $this->max_usage;
    }

    /**
     * Generate unique token
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Get share URL
     */
    public function getShareUrlAttribute(): string
    {
        return url("/guest/{$this->token}");
    }
}
