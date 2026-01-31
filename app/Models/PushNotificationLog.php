<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'notification_id',
        'sent_at',
        'onesignal_id',
        'delivered',
        'delivered_at',
        'clicked',
        'clicked_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'clicked' => 'boolean',
        'clicked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    /**
     * Visszaadja a mai napra vonatkozó push értesítések számát egy userhez.
     *
     * @param int $userId
     * @return int
     */
    public static function getTodayCountForUser(int $userId): int
    {
        // PostgreSQL: DATE(sent_at) = CURRENT_DATE vagy whereDate() (Laravel konvertálja)
        return static::where('user_id', $userId)
            ->whereDate('sent_at', today()) // Laravel: DATE(sent_at) = ?
            ->count();
    }

    /**
     * Visszaadja a user utolsó push értesítését.
     *
     * @param int $userId
     * @return PushNotificationLog|null
     */
    public static function getLastSentForUser(int $userId): ?static
    {
        return static::where('user_id', $userId)
            ->orderBy('sent_at', 'desc')
            ->first();
    }
}
