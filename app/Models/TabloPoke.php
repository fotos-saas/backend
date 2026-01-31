<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloPoke - Peer-to-peer emlékeztető ("bökés") rendszer.
 *
 * @property int $id
 * @property int $from_guest_session_id
 * @property int $target_guest_session_id
 * @property int $tablo_project_id
 * @property string $category voting | photoshoot | image_selection | general
 * @property string $message_type preset | custom
 * @property string|null $preset_key
 * @property string|null $custom_message
 * @property string|null $emoji
 * @property string|null $text
 * @property string $status sent | pending | resolved | expired
 * @property string|null $reaction 💀 | 😭 | 🫡 | ❤️ | 👀
 * @property bool $is_read
 * @property \Carbon\Carbon|null $reacted_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPoke extends Model
{
    use HasFactory;

    // Kategóriák
    public const CATEGORY_VOTING = 'voting';

    public const CATEGORY_PHOTOSHOOT = 'photoshoot';

    public const CATEGORY_IMAGE_SELECTION = 'image_selection';

    public const CATEGORY_GENERAL = 'general';

    // Státuszok
    public const STATUS_SENT = 'sent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_EXPIRED = 'expired';

    // Reakciók
    public const REACTIONS = ['💀', '😭', '🫡', '❤️', '👀'];

    // Limitek
    public const DAILY_LIMIT = 200;

    public const MAX_POKES_PER_USER = 50;

    protected $fillable = [
        'from_guest_session_id',
        'target_guest_session_id',
        'tablo_project_id',
        'category',
        'message_type',
        'preset_key',
        'custom_message',
        'emoji',
        'text',
        'status',
        'reaction',
        'is_read',
        'reacted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'reacted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Ki küldte a bökést
     */
    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'from_guest_session_id');
    }

    /**
     * Ki kapta a bökést
     */
    public function targetSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'target_guest_session_id');
    }

    /**
     * Projekt
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Projekthez tartozó bökések
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('tablo_project_id', $projectId);
    }

    /**
     * Felhasználó által küldött bökések
     */
    public function scopeSentBy($query, int $sessionId)
    {
        return $query->where('from_guest_session_id', $sessionId);
    }

    /**
     * Felhasználónak küldött bökések
     */
    public function scopeReceivedBy($query, int $sessionId)
    {
        return $query->where('target_guest_session_id', $sessionId);
    }

    /**
     * Olvasatlan bökések
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Adott státuszú bökések
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Mai bökések
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Adott kategóriájú bökések
     */
    public function scopeWithCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Olvasottnak jelölés
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update(['is_read' => true]);
    }

    /**
     * Reakció hozzáadása
     */
    public function addReaction(string $reaction): bool
    {
        if (! in_array($reaction, self::REACTIONS)) {
            return false;
        }

        return $this->update([
            'reaction' => $reaction,
            'reacted_at' => now(),
            'is_read' => true, // Reakció implicit olvasás
        ]);
    }

    /**
     * Megoldottnak jelölés
     */
    public function markAsResolved(): bool
    {
        return $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Van-e már reakció
     */
    public function hasReaction(): bool
    {
        return ! empty($this->reaction);
    }

    /**
     * Olvasva van-e
     */
    public function isRead(): bool
    {
        return $this->is_read;
    }

    /**
     * API response formátum
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'from' => [
                'id' => $this->fromSession->id,
                'name' => $this->fromSession->guest_name,
            ],
            'target' => [
                'id' => $this->targetSession->id,
                'name' => $this->targetSession->guest_name,
            ],
            'category' => $this->category,
            'messageType' => $this->message_type,
            'emoji' => $this->emoji,
            'text' => $this->text,
            'status' => $this->status,
            'reaction' => $this->reaction,
            'isRead' => $this->is_read,
            'reactedAt' => $this->reacted_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
