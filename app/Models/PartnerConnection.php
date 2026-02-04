<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partner Connection Model
 *
 * Fotós ↔ Nyomda kapcsolatok pivot tábla.
 * Kétirányú kapcsolat kezelése.
 *
 * @property int $id
 * @property int $photo_studio_id
 * @property int $print_shop_id
 * @property string $initiated_by photo_studio | print_shop
 * @property string $status pending | active | inactive
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PartnerConnection extends Model
{
    use HasFactory;

    // Ki kezdeményezte
    public const INITIATED_BY_PHOTO_STUDIO = 'photo_studio';
    public const INITIATED_BY_PRINT_SHOP = 'print_shop';

    // Státuszok
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'photo_studio_id',
        'print_shop_id',
        'initiated_by',
        'status',
    ];

    /**
     * Fotós partner kapcsolat
     */
    public function photoStudio(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'photo_studio_id');
    }

    /**
     * Nyomda partner kapcsolat
     */
    public function printShop(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'print_shop_id');
    }

    /**
     * Aktív-e a kapcsolat?
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Függőben van-e?
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Kapcsolat aktiválása
     */
    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Kapcsolat deaktiválása
     */
    public function deactivate(): void
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
    }

    /**
     * Státusz magyar megnevezése
     */
    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Függőben',
            self::STATUS_ACTIVE => 'Aktív',
            self::STATUS_INACTIVE => 'Inaktív',
            default => $this->status,
        };
    }

    // ============ Scopes ============

    /**
     * Aktív kapcsolatok
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Függőben lévő kapcsolatok
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
