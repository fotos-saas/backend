<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TabloBadge - Badge (kitüntetés) model
 *
 * @property int $id
 * @property string $key Egyedi azonosító
 * @property string $name Badge neve
 * @property string $description Leírás
 * @property string $tier Szint (bronze/silver/gold)
 * @property string $icon Heroicon név
 * @property string $color Tailwind szín
 * @property int $points Pont jutalom
 * @property array $criteria Kritériumok JSON
 * @property int $sort_order Rendezési sorrend
 * @property bool $is_active Aktív-e
 */
class TabloBadge extends Model
{
    protected $table = 'tablo_badges';

    protected $fillable = [
        'key',
        'name',
        'description',
        'tier',
        'icon',
        'color',
        'points',
        'criteria',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'points' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Felhasználók, akik megszerezték ezt a badge-et
     */
    public function userBadges(): HasMany
    {
        return $this->hasMany(TabloUserBadge::class, 'tablo_badge_id');
    }

    /**
     * Csak aktív badge-ek
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Tier szerinti szűrés
     */
    public function scopeTier(Builder $query, string $tier): void
    {
        $query->where('tier', $tier);
    }

    /**
     * Rendezett lista
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Badge tier megjelenítési név
     */
    public function getTierLabelAttribute(): string
    {
        return match ($this->tier) {
            'bronze' => 'Bronz',
            'silver' => 'Ezüst',
            'gold' => 'Arany',
            default => $this->tier,
        };
    }

    /**
     * Teljes Heroicon osztály
     */
    public function getIconClassAttribute(): string
    {
        return $this->icon;
    }

    /**
     * Tailwind szín osztály
     */
    public function getColorClassAttribute(): string
    {
        return "text-{$this->color}";
    }
}
