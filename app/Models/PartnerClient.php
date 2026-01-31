<?php

namespace App\Models;

use App\Traits\HasAccessCode;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * PartnerClient Model
 *
 * Partner (fotós) ügyfelei, akik kódos vagy email/jelszó belépéssel tudnak albumokat kezelni.
 *
 * Ha a kliens regisztrál (is_registered = true), a kód alapú belépés megszűnik,
 * és csak email/jelszóval léphet be.
 */
class PartnerClient extends Model implements AuthenticatableContract
{
    use HasFactory;
    use HasAccessCode;
    use Authenticatable;

    protected $fillable = [
        'tablo_partner_id',
        'name',
        'email',
        'phone',
        'password',
        'is_registered',
        'registered_at',
        'email_verified_at',
        'wants_notifications',
        'allow_registration',
        'access_code',
        'access_code_enabled',
        'access_code_expires_at',
        'note',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'access_code_enabled' => 'boolean',
        'access_code_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_registered' => 'boolean',
        'registered_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'wants_notifications' => 'boolean',
        'allow_registration' => 'boolean',
    ];

    /**
     * Get the partner this client belongs to
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    /**
     * Get albums for this client
     */
    public function albums(): HasMany
    {
        return $this->hasMany(PartnerAlbum::class);
    }

    /**
     * Get album progress records for this client
     */
    public function albumProgress(): HasMany
    {
        return $this->hasMany(PartnerAlbumProgress::class);
    }

    /**
     * Scope: Filter by partner
     */
    public function scopeByPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('tablo_partner_id', $partnerId);
    }

    /**
     * Record login time
     */
    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get active albums count
     */
    public function getActiveAlbumsCountAttribute(): int
    {
        return $this->albums()->where('status', '!=', 'completed')->count();
    }

    /**
     * Check if client has any active albums
     */
    public function hasActiveAlbums(): bool
    {
        return $this->active_albums_count > 0;
    }

    // ============================================
    // AUTH METHODS
    // ============================================

    /**
     * Jelszó beállítása (automatikus hash-elés)
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['password'] = Hash::needsRehash($value)
                ? Hash::make($value)
                : $value;
        } else {
            $this->attributes['password'] = null;
        }
    }

    /**
     * Check if client can login with password (registered)
     */
    public function canLoginWithPassword(): bool
    {
        return $this->is_registered && !empty($this->password);
    }

    /**
     * Check if client can login with access code
     * Ha regisztrált, a kód alapú belépés LETILTVA!
     */
    public function canLoginWithCode(): bool
    {
        if ($this->is_registered) {
            return false;
        }

        return $this->access_code_enabled
            && $this->access_code
            && (!$this->access_code_expires_at || $this->access_code_expires_at > now());
    }

    /**
     * Register client with password
     * Letiltja a kód alapú belépést és beállítja a regisztráció időpontját.
     */
    public function register(string $password): void
    {
        $this->update([
            'password' => $password,
            'is_registered' => true,
            'registered_at' => now(),
            // Kód alapú belépés letiltása
            'access_code_enabled' => false,
        ]);
    }

    /**
     * Check if registration is allowed for this client
     * A partner állítja be a kliensre, nem az albumra.
     */
    public function hasAlbumWithRegistrationAllowed(): bool
    {
        return $this->allow_registration;
    }

    /**
     * Check if registration is allowed (alias for readability)
     */
    public function canRegister(): bool
    {
        return $this->allow_registration && !$this->is_registered;
    }

    /**
     * Get albums that are available for download
     * Figyelembe veszi az album download_days beállítást.
     */
    public function getDownloadableAlbums(): \Illuminate\Database\Eloquent\Collection
    {
        // Csak regisztrált kliensek tölthetnek le
        if (!$this->is_registered) {
            return collect();
        }

        return $this->albums()
            ->where('status', PartnerAlbum::STATUS_COMPLETED)
            ->where(function ($query) {
                $query->whereNull('download_days')
                    ->orWhere(function ($q) {
                        $q->whereRaw("finalized_at + (download_days || ' days')::interval > now()");
                    });
            })
            ->get();
    }

    /**
     * Scope: Filter registered clients
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->where('is_registered', true);
    }

    /**
     * Scope: Filter unregistered (guest) clients
     */
    public function scopeGuest(Builder $query): Builder
    {
        return $query->where('is_registered', false);
    }

    /**
     * Scope: Find by email (for login)
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }
}
