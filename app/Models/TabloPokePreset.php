<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TabloPokePreset - Előre definiált bökés üzenetek.
 *
 * @property int $id
 * @property string $key Egyedi kulcs
 * @property string $emoji
 * @property string $text_hu Magyar szöveg
 * @property string|null $category
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPokePreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'emoji',
        'text_hu',
        'category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Aktív presetek
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Rendezve
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Kategóriához tartozó presetek
     */
    public function scopeForCategory($query, ?string $category)
    {
        if ($category) {
            return $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhereNull('category');
            });
        }

        return $query;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Preset keresése kulcs alapján
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    /**
     * Összes aktív preset lekérése
     */
    public static function getActivePresets(?string $category = null): \Illuminate\Support\Collection
    {
        return static::active()
            ->forCategory($category)
            ->ordered()
            ->get();
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * API response formátum
     */
    public function toApiResponse(): array
    {
        return [
            'key' => $this->key,
            'emoji' => $this->emoji,
            'text' => $this->text_hu,
            'category' => $this->category,
        ];
    }
}
