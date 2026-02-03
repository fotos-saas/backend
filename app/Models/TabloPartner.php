<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TabloPartner Model
 *
 * Tablo partnercégek (fotóstúdiók).
 * Az ügyintézők (User-ek) a tablo_partner_id-n keresztül kapcsolódnak.
 */

class TabloPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'slug',
        'local_id',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
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
     * Feature constants
     */
    public const FEATURE_CLIENT_ORDERS = 'client_orders';
}
