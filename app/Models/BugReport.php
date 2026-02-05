<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BugReport extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'reporter_id',
        'title',
        'description',
        'status',
        'priority',
        'answered_by',
        'ai_response',
        'ai_resolved_at',
        'first_viewed_at',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'ai_resolved_at' => 'datetime',
            'first_viewed_at' => 'datetime',
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BugReportAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BugReportComment::class)->orderBy('created_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BugReportStatusHistory::class)->orderBy('created_at');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('first_viewed_at');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function markAsViewed(): void
    {
        if (!$this->first_viewed_at) {
            $this->update(['first_viewed_at' => now()]);
        }
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    // ==========================================
    // STATIC HELPERS
    // ==========================================

    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW => 'Új',
            self::STATUS_IN_PROGRESS => 'Folyamatban',
            self::STATUS_RESOLVED => 'Megoldva',
            self::STATUS_CLOSED => 'Lezárva',
        ];
    }

    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Alacsony',
            self::PRIORITY_MEDIUM => 'Közepes',
            self::PRIORITY_HIGH => 'Magas',
            self::PRIORITY_CRITICAL => 'Kritikus',
        ];
    }
}
