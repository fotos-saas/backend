<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TabloEmailSnippet extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'subject',
        'content',
        'sort_order',
        'is_active',
        'is_featured',
        'is_auto_enabled',
        'auto_trigger',
        'auto_trigger_config',
        'auto_last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_auto_enabled' => 'boolean',
            'auto_trigger_config' => 'array',
            'auto_last_run_at' => 'datetime',
        ];
    }

    /**
     * Auto-generate slug from name if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (TabloEmailSnippet $snippet) {
            if (empty($snippet->slug)) {
                $snippet->slug = Str::slug($snippet->name);
            }
        });
    }

    /**
     * Scope: only active snippets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: auto-enabled snippets.
     */
    public function scopeAutoEnabled($query)
    {
        return $query->where('is_auto_enabled', true);
    }

    /**
     * Rövidíti az iskola nevét a tárgyhoz.
     * Pl. "Boronkay György Műszaki Technikum és Gimnázium" → "Boronkay György Műszaki..."
     */
    public static function shortenSchoolName(string $name, int $maxLength = 35): string
    {
        if (mb_strlen($name) <= $maxLength) {
            return $name;
        }

        // Szó határon vágjuk el
        $truncated = mb_substr($name, 0, $maxLength - 3);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > 10) {
            return mb_substr($truncated, 0, $lastSpace) . '...';
        }

        return $truncated . '...';
    }

    /**
     * Replace placeholders in content with actual values.
     */
    public function renderContent(array $data): string
    {
        $content = $this->content;

        $placeholders = [
            '{nev}' => $data['nev'] ?? 'Tisztelt Címzett',
            '{osztaly}' => $data['osztaly'] ?? '',
            '{iskola}' => $data['iskola'] ?? '',
            '{iskola_rovid}' => self::shortenSchoolName($data['iskola'] ?? ''),
            '{ev}' => $data['ev'] ?? '',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Replace placeholders in subject with actual values.
     */
    public function renderSubject(array $data): ?string
    {
        if (!$this->subject) {
            return null;
        }

        $placeholders = [
            '{nev}' => $data['nev'] ?? '',
            '{osztaly}' => $data['osztaly'] ?? '',
            '{iskola}' => $data['iskola'] ?? '',
            '{iskola_rovid}' => self::shortenSchoolName($data['iskola'] ?? ''),
            '{ev}' => $data['ev'] ?? '',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $this->subject);
    }

    /**
     * Get available placeholders with descriptions.
     */
    public static function getAvailablePlaceholders(): array
    {
        return [
            '{nev}' => 'Kapcsolattartó neve',
            '{osztaly}' => 'Osztály neve (pl. 12.B)',
            '{iskola}' => 'Iskola teljes neve',
            '{iskola_rovid}' => 'Iskola rövidített neve (pl. Boronkay Gy...)',
            '{ev}' => 'Évfolyam (pl. 2026)',
        ];
    }
}
