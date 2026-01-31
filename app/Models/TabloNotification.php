<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * TabloNotification - Értesítések (mention, reply, like, badge).
 *
 * @property int $id
 * @property int $tablo_project_id
 * @property string $recipient_type 'contact' vagy 'guest'
 * @property int $recipient_id
 * @property string $type mention|reply|like|badge
 * @property string $title
 * @property string $body
 * @property array|null $data Kontextus JSON
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property bool $is_read
 * @property \Carbon\Carbon|null $read_at
 * @property string|null $action_url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloNotification extends Model
{
    protected $table = 'tablo_notifications';

    // Notification types
    public const TYPE_MENTION = 'mention';

    public const TYPE_REPLY = 'reply';

    public const TYPE_LIKE = 'like';

    public const TYPE_BADGE = 'badge';

    // Newsfeed notification types
    public const TYPE_NEWSFEED_POST = 'newsfeed_post';

    public const TYPE_NEWSFEED_COMMENT = 'newsfeed_comment';

    public const TYPE_NEWSFEED_LIKE = 'newsfeed_like';

    public const TYPE_NEWSFEED_EVENT = 'newsfeed_event';

    // Recipient types
    public const RECIPIENT_TYPE_CONTACT = 'contact';

    public const RECIPIENT_TYPE_GUEST = 'guest';

    protected $fillable = [
        'tablo_project_id',
        'recipient_type',
        'recipient_id',
        'type',
        'title',
        'body',
        'data',
        'notifiable_type',
        'notifiable_id',
        'is_read',
        'read_at',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Projekt kapcsolat
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get notifiable entity (polymorphic)
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Mark as unread
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Scope: unread notifications
     */
    public function scopeUnread(Builder $query): void
    {
        $query->where('is_read', false);
    }

    /**
     * Scope: read notifications
     */
    public function scopeRead(Builder $query): void
    {
        $query->where('is_read', true);
    }

    /**
     * Scope: by type
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope: for recipient
     */
    public function scopeForRecipient(Builder $query, string $recipientType, int $recipientId): void
    {
        $query->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId);
    }

    /**
     * Scope: recent (last 30 days)
     */
    public function scopeRecent(Builder $query, int $days = 30): void
    {
        $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get notification icon based on type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MENTION => 'heroicon-o-at-symbol',
            self::TYPE_REPLY => 'heroicon-o-chat-bubble-left',
            self::TYPE_LIKE, self::TYPE_NEWSFEED_LIKE => 'heroicon-o-heart',
            self::TYPE_BADGE => 'heroicon-o-star',
            self::TYPE_NEWSFEED_POST => 'heroicon-o-newspaper',
            self::TYPE_NEWSFEED_COMMENT => 'heroicon-o-chat-bubble-bottom-center-text',
            self::TYPE_NEWSFEED_EVENT => 'heroicon-o-calendar',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MENTION => 'info',
            self::TYPE_REPLY, self::TYPE_NEWSFEED_COMMENT => 'primary',
            self::TYPE_LIKE, self::TYPE_NEWSFEED_LIKE => 'danger',
            self::TYPE_BADGE => 'warning',
            self::TYPE_NEWSFEED_POST => 'success',
            self::TYPE_NEWSFEED_EVENT => 'info',
            default => 'gray',
        };
    }
}
