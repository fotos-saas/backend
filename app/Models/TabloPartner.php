<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TabloPartner Model
 *
 * Tablo partnercégek (fotóstúdiók és nyomdák).
 * Az ügyintézők (User-ek) a tablo_partner_id-n keresztül kapcsolódnak.
 *
 * Partner típusok:
 * - photo_studio: Fotós partner (előfizetéses modell)
 * - print_shop: Nyomda partner (forgalom alapú díjazás)
 */
class TabloPartner extends Model
{
    use HasFactory;

    // Partner típusok
    public const TYPE_PHOTO_STUDIO = 'photo_studio';
    public const TYPE_PRINT_SHOP = 'print_shop';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'slug',
        'local_id',
        'type',
        'commission_rate',
        'features',
        'partner_id',
    ];

    protected $casts = [
        'features' => 'array',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TabloPartner $partner) {
            if (empty($partner->slug)) {
                $partner->slug = Str::slug($partner->name);
            }
        });

        static::updating(function (TabloPartner $partner) {
            if ($partner->isDirty('name') && ! $partner->isDirty('slug')) {
                $partner->slug = Str::slug($partner->name);
            }
        });
    }

    /**
     * Get the subscription partner (Partner model with billing/branding)
     */
    public function subscriptionPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Get projects for this partner
     */
    public function projects(): HasMany
    {
        return $this->hasMany(TabloProject::class, 'partner_id');
    }

    /**
     * Get schools linked to this partner (many-to-many via partner_schools pivot)
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(TabloSchool::class, 'partner_schools', 'partner_id', 'school_id')
            ->withTimestamps();
    }

    /**
     * Get users (ügyintézők) for this partner
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tablo_partner_id');
    }

    /**
     * Get the projects count
     */
    public function getProjectsCountAttribute(): int
    {
        return $this->projects()->count();
    }

    /**
     * Get contacts for this partner
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(TabloContact::class, 'partner_id');
    }

    /**
     * Get clients for this partner
     */
    public function clients(): HasMany
    {
        return $this->hasMany(PartnerClient::class, 'tablo_partner_id');
    }

    /**
     * Get partner albums
     */
    public function partnerAlbums(): HasMany
    {
        return $this->hasMany(PartnerAlbum::class, 'tablo_partner_id');
    }

    /**
     * Check if partner has a specific feature enabled
     */
    public function hasFeature(string $feature): bool
    {
        return data_get($this->features, $feature, false);
    }

    /**
     * Enable a feature
     */
    public function enableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $features[$feature] = true;
        $this->update(['features' => $features]);
    }

    /**
     * Disable a feature
     */
    public function disableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $features[$feature] = false;
        $this->update(['features' => $features]);
    }

    /**
     * Get active branding data for this partner.
     * Uses direct partner_id FK, with email fallback for legacy data.
     */
    public function getActiveBranding(): ?array
    {
        // Primary: direct FK
        $partner = $this->subscriptionPartner;

        // Fallback: email match (legacy)
        if (!$partner && $this->email) {
            $ownerUser = User::where('email', $this->email)->first();
            $partner = $ownerUser?->partner;
        }

        if (!$partner) {
            return null;
        }

        $branding = $partner->branding;

        if (!$branding || !$branding->is_active) {
            return null;
        }

        return [
            'brandName' => $branding->brand_name,
            'logoUrl' => $branding->getLogoUrl(),
        ];
    }

    /**
     * Feature constants
     */
    public const FEATURE_CLIENT_ORDERS = 'client_orders';

    // ============ Partner Type Methods ============

    /**
     * Fotós partner-e?
     */
    public function isPhotoStudio(): bool
    {
        return $this->type === self::TYPE_PHOTO_STUDIO || $this->type === null;
    }

    /**
     * Nyomda partner-e?
     */
    public function isPrintShop(): bool
    {
        return $this->type === self::TYPE_PRINT_SHOP;
    }

    /**
     * Partner típus magyar megnevezése
     */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PHOTO_STUDIO => 'Fotós Partner',
            self::TYPE_PRINT_SHOP => 'Nyomda Partner',
            default => 'Fotós Partner',
        };
    }

    // ============ Team Members (Csapattagok) ============

    /**
     * Csapattagok (szabadúszó modell)
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(PartnerTeamMember::class, 'partner_id');
    }

    /**
     * Aktív csapattagok
     */
    public function activeTeamMembers(): HasMany
    {
        return $this->teamMembers()->where('is_active', true);
    }

    /**
     * Csapattagok User-ként (BelongsToMany)
     */
    public function teamMemberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_team_members', 'partner_id', 'user_id')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    /**
     * Meghívások
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(PartnerInvitation::class, 'partner_id');
    }

    /**
     * Függőben lévő meghívások
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->where('status', PartnerInvitation::STATUS_PENDING);
    }

    // ============ Partner Connections (Nyomda ↔ Fotós) ============

    /**
     * Kapcsolt nyomdák (ha fotós partner)
     */
    public function connectedPrintShops(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloPartner::class,
            'partner_connections',
            'photo_studio_id',
            'print_shop_id'
        )
            ->wherePivot('status', PartnerConnection::STATUS_ACTIVE)
            ->withPivot('initiated_by', 'status')
            ->withTimestamps();
    }

    /**
     * Kapcsolt fotósok (ha nyomda partner)
     */
    public function connectedPhotoStudios(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloPartner::class,
            'partner_connections',
            'print_shop_id',
            'photo_studio_id'
        )
            ->wherePivot('status', PartnerConnection::STATUS_ACTIVE)
            ->withPivot('initiated_by', 'status')
            ->withTimestamps();
    }

    /**
     * Függőben lévő kapcsolat kérések (bejövő)
     */
    public function pendingConnectionRequests(): HasMany
    {
        if ($this->isPhotoStudio()) {
            return $this->hasMany(PartnerConnection::class, 'photo_studio_id')
                ->where('status', PartnerConnection::STATUS_PENDING)
                ->where('initiated_by', PartnerConnection::INITIATED_BY_PRINT_SHOP);
        }

        return $this->hasMany(PartnerConnection::class, 'print_shop_id')
            ->where('status', PartnerConnection::STATUS_PENDING)
            ->where('initiated_by', PartnerConnection::INITIATED_BY_PHOTO_STUDIO);
    }
}
