<?php

namespace App\Models;

use App\Enums\InvoicingProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        'default_max_retouch_photos',
        'default_gallery_deadline_days',
        'default_free_edit_window_hours',
        'billing_enabled',
        'payment_stripe_public_key',
        'payment_stripe_secret_key',
        'payment_stripe_webhook_secret',
        'payment_stripe_enabled',
        'default_zip_content',
        'default_file_naming',
        'export_always_ask',
        'invoice_provider',
        'invoice_enabled',
        'invoice_api_key',
        'szamlazz_bank_name',
        'szamlazz_bank_account',
        'szamlazz_reply_email',
        'billingo_block_id',
        'billingo_bank_account_id',
        'invoice_prefix',
        'invoice_currency',
        'invoice_language',
        'invoice_due_days',
        'invoice_vat_percentage',
        'invoice_comment',
        'invoice_eu_vat',
    ];

    protected $hidden = [
        'payment_stripe_secret_key',
        'payment_stripe_webhook_secret',
        'invoice_api_key',
    ];

    protected $casts = [
        'features' => 'array',
        'commission_rate' => 'decimal:2',
        'default_max_retouch_photos' => 'integer',
        'default_gallery_deadline_days' => 'integer',
        'default_free_edit_window_hours' => 'integer',
        'billing_enabled' => 'boolean',
        'payment_stripe_enabled' => 'boolean',
        'export_always_ask' => 'boolean',
        'invoice_provider' => InvoicingProviderType::class,
        'invoice_enabled' => 'boolean',
        'invoice_due_days' => 'integer',
        'invoice_vat_percentage' => 'decimal:2',
        'invoice_eu_vat' => 'boolean',
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
            ->withPivot('linked_group')
            ->withTimestamps();
    }

    /**
     * Összekapcsolt iskolák ID-jainak lekérdezése.
     * Ha az iskola egy linked_group-ban van, visszaadja a csoport összes iskoláját.
     * Ha nincs csoportban, csak az adott iskola ID-t adja vissza.
     */
    public function getLinkedSchoolIds(int $schoolId): array
    {
        $linkedGroup = DB::table('partner_schools')
            ->where('partner_id', $this->id)
            ->where('school_id', $schoolId)
            ->value('linked_group');

        if (!$linkedGroup) {
            return [$schoolId];
        }

        return DB::table('partner_schools')
            ->where('partner_id', $this->id)
            ->where('linked_group', $linkedGroup)
            ->pluck('school_id')
            ->toArray();
    }

    /**
     * Összekapcsolt tanárok ID-jainak lekérdezése.
     * Ha a tanár egy linked_group-ban van, visszaadja a csoport összes tanárát.
     * Ha nincs csoportban, csak az adott tanár ID-t adja vissza.
     */
    public function getLinkedTeacherIds(int $teacherId): array
    {
        $linkedGroup = TeacherArchive::where('partner_id', $this->id)
            ->where('id', $teacherId)
            ->value('linked_group');

        if (!$linkedGroup) {
            return [$teacherId];
        }

        return TeacherArchive::where('partner_id', $this->id)
            ->where('linked_group', $linkedGroup)
            ->pluck('id')
            ->toArray();
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
     * Get teachers for this partner
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(TeacherArchive::class, 'partner_id');
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

        // Ha a partnernek nincs branding feature-je (addon lemondva / csomag nem tartalmazza)
        if (!$partner->hasFeature('branding')) {
            return null;
        }

        $branding = $partner->branding;

        if (!$branding || !$branding->is_active) {
            return null;
        }

        return [
            'brandName' => $branding->brand_name,
            'logoUrl' => $branding->getLogoUrl(),
            'hideBrandName' => $branding->hide_brand_name,
        ];
    }

    /**
     * Branding hozzáfűzése egy response tömbhöz, ha elérhető.
     */
    public static function appendBranding(array &$data, ?self $partner): void
    {
        if ($partner) {
            $branding = $partner->getActiveBranding();
            if ($branding) {
                $data['branding'] = $branding;
            }
        }
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

    // ============ Partner Services (Szolgáltatás katalógus) ============

    public function partnerServices(): HasMany
    {
        return $this->hasMany(PartnerService::class, 'partner_id');
    }

    // ============ Invoices (Számlák) ============

    public function invoices(): HasMany
    {
        return $this->hasMany(TabloInvoice::class, 'tablo_partner_id');
    }

    public function getDecryptedApiKey(): ?string
    {
        if (! $this->invoice_api_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->invoice_api_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function setEncryptedApiKey(?string $plainKey): void
    {
        $this->invoice_api_key = $plainKey ? Crypt::encryptString($plainKey) : null;
    }

    public function hasInvoicingEnabled(): bool
    {
        return $this->invoice_enabled && $this->invoice_api_key !== null;
    }

    // ============ Payment Stripe (Partner Stripe kulcsok) ============

    public function getDecryptedStripePublicKey(): ?string
    {
        if (! $this->payment_stripe_public_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_public_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function getDecryptedStripeSecretKey(): ?string
    {
        if (! $this->payment_stripe_secret_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_secret_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function getDecryptedStripeWebhookSecret(): ?string
    {
        if (! $this->payment_stripe_webhook_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_webhook_secret);
        } catch (\Exception) {
            return null;
        }
    }

    public function setEncryptedStripeKeys(?string $publicKey, ?string $secretKey, ?string $webhookSecret): void
    {
        $this->payment_stripe_public_key = $publicKey ? Crypt::encryptString($publicKey) : null;
        $this->payment_stripe_secret_key = $secretKey ? Crypt::encryptString($secretKey) : null;
        $this->payment_stripe_webhook_secret = $webhookSecret ? Crypt::encryptString($webhookSecret) : null;
    }

    public function hasStripePaymentEnabled(): bool
    {
        return $this->payment_stripe_enabled
            && $this->payment_stripe_public_key !== null
            && $this->payment_stripe_secret_key !== null;
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
