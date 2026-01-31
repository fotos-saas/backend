<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TabloStatus - Predefined statuses for tablo projects.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Tailwind color mapping for frontend.
     */
    public const COLOR_MAP = [
        'gray' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Szürke'],
        'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Kék'],
        'amber' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Sárga'],
        'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Zöld'],
        'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'label' => 'Lila'],
        'red' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Piros'],
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TabloStatus $status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });

        static::updating(function (TabloStatus $status) {
            if ($status->isDirty('name') && ! $status->isDirty('slug')) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    /**
     * Get tablo projects with this status.
     */
    public function tabloProjects(): HasMany
    {
        return $this->hasMany(TabloProject::class);
    }

    /**
     * Scope for active statuses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered statuses.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get Tailwind CSS classes for this status color.
     */
    public function getTailwindClasses(): array
    {
        return self::COLOR_MAP[$this->color] ?? self::COLOR_MAP['gray'];
    }

    /**
     * Get color options for Filament select.
     */
    public static function getColorOptions(): array
    {
        return collect(self::COLOR_MAP)->mapWithKeys(fn ($value, $key) => [
            $key => $value['label'],
        ])->toArray();
    }

    /**
     * Convert to API response format.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'icon' => $this->icon,
        ];
    }
}
