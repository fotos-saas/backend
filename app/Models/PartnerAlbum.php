<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * PartnerAlbum Model
 *
 * Partner által létrehozott album az ügyfél számára.
 * Két típus: 'selection' (egyszerű képválasztás) és 'tablo' (teljes workflow).
 */
class PartnerAlbum extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const TYPE_SELECTION = 'selection';
    public const TYPE_TABLO = 'tablo';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CLAIMING = 'claiming';
    public const STATUS_RETOUCH = 'retouch';
    public const STATUS_TABLO = 'tablo';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'tablo_partner_id',
        'partner_client_id',
        'name',
        'type',
        'status',
        'max_selections',
        'min_selections',
        'max_retouch_photos',
        'settings',
        'finalized_at',
        'expires_at',
        'download_days',
        'allow_download',
    ];

    protected $casts = [
        'settings' => 'array',
        'finalized_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_selections' => 'integer',
        'min_selections' => 'integer',
        'max_retouch_photos' => 'integer',
        'download_days' => 'integer',
        'allow_download' => 'boolean',
    ];

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');
    }

    /**
     * Register media conversions
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(800)
            ->sharpen(5)
            ->nonQueued();
    }

    /**
     * Get the partner
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }

    /**
     * Get progress for this album (tablo type only)
     */
    public function progress(): HasOne
    {
        return $this->hasOne(PartnerAlbumProgress::class);
    }

    /**
     * Scope: Filter by partner
     */
    public function scopeByPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('tablo_partner_id', $partnerId);
    }

    /**
     * Scope: Filter by client
     */
    public function scopeByClient(Builder $query, int $clientId): Builder
    {
        return $query->where('partner_client_id', $clientId);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check if this is a selection type album
     */
    public function isSelectionType(): bool
    {
        return $this->type === self::TYPE_SELECTION;
    }

    /**
     * Check if this is a tablo type album
     */
    public function isTabloType(): bool
    {
        return $this->type === self::TYPE_TABLO;
    }

    /**
     * Kliens-oldali tömb reprezentáció (login/session response-okhoz).
     */
    public function toClientArray(bool $includeDownload = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'photosCount' => $this->photos_count,
            'maxSelections' => $this->max_selections,
            'minSelections' => $this->min_selections,
            'isCompleted' => $this->isCompleted(),
        ];

        if ($includeDownload) {
            $data['canDownload'] = $this->canDownload();
        }

        return $data;
    }

    /**
     * Check if album is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if album is in draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if album is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    /**
     * Get photos count
     */
    public function getPhotosCountAttribute(): int
    {
        return $this->getMedia('photos')->count();
    }

    /**
     * Get all photos with URLs
     */
    public function getPhotosWithUrls(): array
    {
        return $this->getMedia('photos')->map(function (Media $media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'title' => $media->getCustomProperty('iptc_title') ?? pathinfo($media->file_name, PATHINFO_FILENAME),
                'original_url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'order' => $media->order_column,
            ];
        })->toArray();
    }

    /**
     * Finalize the album
     */
    public function finalize(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'finalized_at' => now(),
        ]);
    }

    /**
     * Get setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    // ============================================
    // DOWNLOAD & REGISTRATION METHODS
    // ============================================

    /**
     * Check if download is available for this album
     * Figyelembe veszi az allow_download és download_days beállítást.
     */
    public function canDownload(): bool
    {
        // Partner engedélyezte-e a letöltést?
        if (!$this->allow_download) {
            return false;
        }

        // Csak véglegesített albumok tölthetők le
        if (!$this->isCompleted() || !$this->finalized_at) {
            return false;
        }

        // Ha nincs időkorlát, mindig letölthető
        if (!$this->download_days) {
            return true;
        }

        // Ellenőrizzük, hogy a letöltési idő nem járt-e le
        $expiresAt = $this->finalized_at->addDays($this->download_days);

        return now()->lt($expiresAt);
    }

    /**
     * Get download expiry date
     */
    public function getDownloadExpiresAt(): ?\Carbon\Carbon
    {
        if (!$this->finalized_at || !$this->download_days) {
            return null;
        }

        return $this->finalized_at->addDays($this->download_days);
    }

    /**
     * Get days remaining for download
     */
    public function getDownloadDaysRemaining(): ?int
    {
        $expiresAt = $this->getDownloadExpiresAt();

        if (!$expiresAt) {
            return null; // Korlátlan
        }

        $remaining = now()->diffInDays($expiresAt, false);

        return max(0, (int) $remaining);
    }
}
