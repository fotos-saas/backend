<?php

namespace App\Models;

use App\Enums\TabloModeType;
use App\Traits\HasAccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkSession extends Model
{
    use HasAccessCode, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'digit_code_enabled',
        'digit_code',
        'digit_code_expires_at',
        'share_enabled',
        'share_token',
        'share_expires_at',
        'allow_invitations',
        'status',
        'coupon_policy',
        'allowed_coupon_ids',
        'package_id',
        'price_list_id',
        'is_tablo_mode',
        'max_retouch_photos',
        'parent_work_session_id',
        'tablo_mode_type',
        'extra_photo_price_list_id',
        'extra_photo_print_size_id',
        'extra_pricing_snapshot',
        'allowed_package_ids',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digit_code_enabled' => 'boolean',
            'share_enabled' => 'boolean',
            'digit_code_expires_at' => 'datetime',
            'share_expires_at' => 'datetime',
            'allow_invitations' => 'boolean',
            'allowed_coupon_ids' => 'array',
            'is_tablo_mode' => 'boolean',
            'tablo_mode_type' => TabloModeType::class,
            'extra_pricing_snapshot' => 'array',
            'allowed_package_ids' => 'array',
        ];
    }

    /**
     * Get the albums associated with the work session.
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)->withTimestamps();
    }

    /**
     * Get the users associated with the work session.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_session_user')
            ->withTimestamps();
    }

    /**
     * Package relationship
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Price list relationship
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Extra foto árlista (flexible módhoz)
     */
    public function extraPhotoPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'extra_photo_price_list_id');
    }

    /**
     * Extra foto papírméret (flexible módhoz)
     */
    public function extraPhotoPrintSize(): BelongsTo
    {
        return $this->belongsTo(PrintSize::class, 'extra_photo_print_size_id');
    }

    /**
     * Parent work session relationship
     */
    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class, 'parent_work_session_id');
    }

    /**
     * Child work sessions relationship
     */
    public function childSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'parent_work_session_id');
    }

    /**
     * Generate unique 6-digit access code.
     * Alias for trait method - backward compatibility.
     *
     * @return string 6-digit code
     */
    public function generateDigitCode(): string
    {
        return $this->generateAccessCode();
    }

    /**
     * Generate unique share token (32 characters).
     *
     * @return string Share token
     */
    public function generateShareToken(): string
    {
        return Str::random(32);
    }

    /**
     * Scope query to active work sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to find work session by digit code.
     * Uses trait's scopeByAccessCode + adds status check.
     */
    public function scopeByDigitCode($query, string $code)
    {
        return $this->scopeByAccessCode($query, $code)
            ->where('status', 'active');
    }

    /**
     * Scope query to find work session by share token.
     */
    public function scopeByShareToken($query, string $token)
    {
        return $query->where('share_token', $token)
            ->where('share_enabled', true)
            ->where(function ($q) {
                $q->whereNull('share_expires_at')
                    ->orWhere('share_expires_at', '>', now());
            });
    }

    /**
     * Check if digit code is valid (not expired).
     * Alias for trait method - backward compatibility.
     */
    public function hasValidDigitCode(): bool
    {
        return $this->hasValidAccessCode();
    }

    /**
     * Revoke all Sanctum tokens associated with this work session.
     * Used when digit code is disabled or work session is deleted.
     */
    public function revokeUserTokens(): void
    {
        \Laravel\Sanctum\PersonalAccessToken::where('work_session_id', $this->id)
            ->delete();
    }

    /**
     * Check if share token is valid (not expired).
     */
    public function hasValidShareToken(): bool
    {
        if (! $this->share_enabled || ! $this->share_token) {
            return false;
        }

        if (! $this->share_expires_at) {
            return true;
        }

        return $this->share_expires_at->isFuture();
    }

    /**
     * Get full share URL for this work session.
     */
    public function getShareUrl(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return config('app.frontend_url', config('app.url')).'/share/'.$this->share_token;
    }

    /**
     * Check if coupon is allowed for this work session
     */
    public function isCouponAllowed(Coupon $coupon): bool
    {
        return match ($this->coupon_policy) {
            'all' => true,
            'none' => false,
            'specific' => in_array($coupon->id, $this->allowed_coupon_ids ?? []),
            default => true,
        };
    }

    /**
     * Parent work session relationship
     */
    public function parentWorkSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class, 'parent_work_session_id');
    }

    /**
     * Child work sessions relationship
     */
    public function childWorkSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'parent_work_session_id');
    }

    /**
     * Mutator: extra_photo_price_list_id beállítása és automatikus snapshot
     */
    public function setExtraPhotoPriceListIdAttribute($value): void
    {
        // Értékadás
        $this->attributes['extra_photo_price_list_id'] = $value;

        // Ha flexible mód + van print_size_id → készíts snapshot-ot
        if ($this->tablo_mode_type === TabloModeType::FLEXIBLE &&
            $value &&
            isset($this->attributes['extra_photo_print_size_id']) &&
            $this->attributes['extra_photo_print_size_id']) {
            $this->createPricingSnapshot();
        }
    }

    /**
     * Mutator: extra_photo_print_size_id beállítása és automatikus snapshot
     */
    public function setExtraPhotoPrintSizeIdAttribute($value): void
    {
        // Értékadás
        $this->attributes['extra_photo_print_size_id'] = $value;

        // Ha flexible mód + van price_list_id → készíts snapshot-ot
        if ($this->tablo_mode_type === TabloModeType::FLEXIBLE &&
            $value &&
            isset($this->attributes['extra_photo_price_list_id']) &&
            $this->attributes['extra_photo_price_list_id']) {
            $this->createPricingSnapshot();
        }
    }

    /**
     * Snapshot készítés historikus árazáshoz
     */
    protected function createPricingSnapshot(): void
    {
        $price = Price::where('price_list_id', $this->attributes['extra_photo_price_list_id'])
            ->where('print_size_id', $this->attributes['extra_photo_print_size_id'])
            ->with(['priceList', 'printSize'])
            ->first();

        if (! $price) {
            return;
        }

        $this->attributes['extra_pricing_snapshot'] = json_encode([
            'price_list_id' => $price->price_list_id,
            'price_list_name' => $price->priceList->name,
            'print_size_id' => $price->print_size_id,
            'print_size_name' => $price->printSize->name,
            'unit_price' => $price->price,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Visszaadja az extra fotó egységárát (snapshot vagy aktuális)
     */
    public function getExtraPhotoPrice(): int
    {
        // Ha van snapshot → azt használjuk
        if ($this->extra_pricing_snapshot) {
            return $this->extra_pricing_snapshot['unit_price'] ?? 0;
        }

        // Ha nincs → lekérjük az aktuális árat
        if ($this->extra_photo_price_list_id && $this->extra_photo_print_size_id) {
            return Price::where('price_list_id', $this->extra_photo_price_list_id)
                ->where('print_size_id', $this->extra_photo_print_size_id)
                ->value('price') ?? 0;
        }

        return 0;
    }

    /**
     * Ellenőrzi, hogy fix tábló mód van-e beállítva
     */
    public function isFixedTabloMode(): bool
    {
        return $this->tablo_mode_type === TabloModeType::FIXED;
    }

    /**
     * Ellenőrzi, hogy flexible tábló mód van-e beállítva
     */
    public function isFlexibleTabloMode(): bool
    {
        return $this->tablo_mode_type === TabloModeType::FLEXIBLE;
    }

    /**
     * Ellenőrzi, hogy package-based tábló mód van-e beállítva
     */
    public function isPackageBasedTabloMode(): bool
    {
        return $this->tablo_mode_type === TabloModeType::PACKAGES;
    }
}
