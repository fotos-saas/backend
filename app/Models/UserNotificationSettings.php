<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationSettings extends Model
{
    protected $fillable = [
        'user_id',
        'push_enabled',
        'mode',
        'categories',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'categories' => 'array', // JSONB -> array
        'quiet_hours_enabled' => 'boolean',
        // PostgreSQL TIME típus, Laravel-ben cast nélkül string formátumban jön (HH:MM:SS)
        // Ha kell: Carbon::createFromTimeString($this->quiet_hours_start)
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ellenőrzi, hogy adott kategória engedélyezett-e a user beállításai szerint.
     *
     * @param string $category
     * @return bool
     */
    public function isCategoryEnabled(string $category): bool
    {
        // V1: normal/quiet módok
        // V2-ben bővíthető: 'all', 'custom' módokkal

        // Mode-based categories
        $modeCategories = config("notifications.modes.{$this->mode}.categories", []);
        return in_array($category, $modeCategories) || in_array('all', $modeCategories);
    }

    /**
     * Visszaadja a maximálisan engedélyezett push értesítések számát naponta.
     *
     * @return int
     */
    public function getMaxPushPerDay(): int
    {
        return config("notifications.modes.{$this->mode}.maxPushPerDay", 3);
    }

    /**
     * Ellenőrzi, hogy jelenleg csendes óra van-e.
     *
     * @return bool
     */
    public function isQuietHoursActive(): bool
    {
        if (!$this->quiet_hours_enabled || !$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = now()->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Ha az időszak átnyúlik éjfélre (pl. 23:00 - 07:00)
        if ($start > $end) {
            return $now >= $start || $now < $end;
        }

        // Normál esetben (pl. 14:00 - 16:00)
        return $now >= $start && $now < $end;
    }
}
