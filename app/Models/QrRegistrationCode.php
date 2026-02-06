<?php

namespace App\Models;

use App\Enums\QrCodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * QR Registration Code model for guest self-registration.
 *
 * @property int $id
 * @property int $tablo_project_id
 * @property string $code 8 character alphanumeric code (ABC12345)
 * @property bool $is_active
 * @property \Carbon\Carbon|null $expires_at
 * @property int $usage_count
 * @property int|null $max_usages null = unlimited
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class QrRegistrationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'code',
        'type',
        'is_active',
        'expires_at',
        'usage_count',
        'max_usages',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'type' => QrCodeType::class,
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
            'expires_at' => 'datetime',
            'usage_count' => 'integer',
            'max_usages' => 'integer',
        ];
    }

    /**
     * Get the project this code belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Generate a unique 8-character code (ABC12345 format).
     */
    public static function generateCode(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // No I, O (avoid confusion with 1, 0)
        $numbers = '23456789'; // No 0, 1 (avoid confusion with O, I)

        do {
            $code = '';
            // 3 letters
            for ($i = 0; $i < 3; $i++) {
                $code .= $letters[random_int(0, strlen($letters) - 1)];
            }
            // 5 numbers
            for ($i = 0; $i < 5; $i++) {
                $code .= $numbers[random_int(0, strlen($numbers) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if the code is valid (active, not expired, within usage limit).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_usages !== null && $this->usage_count >= $this->max_usages) {
            return false;
        }

        return true;
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Deactivate this code.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get the registration URL for this code.
     */
    public function getRegistrationUrl(): string
    {
        return config('app.frontend_tablo_url').'/tablo/register?code='.$this->code;
    }

    /**
     * Get guest sessions that registered with this code.
     */
    public function registeredSessions(): HasMany
    {
        return $this->hasMany(TabloGuestSession::class, 'qr_registration_code_id');
    }

    /**
     * Scope for a specific type.
     */
    public function scopeOfType($query, QrCodeType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope for pinned codes.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for active codes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid codes (active, not expired, within usage limit).
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_usages')
                    ->orWhereColumn('usage_count', '<', 'max_usages');
            });
    }

    /**
     * Find a valid code by its string.
     */
    public static function findValidCode(string $code): ?self
    {
        return static::valid()
            ->where('code', strtoupper($code))
            ->first();
    }
}
