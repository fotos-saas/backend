<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabloUserProgress extends Model
{
    protected $table = 'tablo_user_progress';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINALIZED = 'finalized';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tablo_gallery_id', // NEW: Gallery-based workflow
        'work_session_id', // LEGACY: Kept for backward compatibility
        'child_work_session_id', // LEGACY: Kept for backward compatibility
        'current_step',
        'steps_data',
        'cart_comment',
        'retouch_photo_ids',
        'tablo_photo_id',
        'workflow_status',
        'finalized_at',
        'modification_count',
        'last_modification_paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps_data' => 'array',
            'retouch_photo_ids' => 'array',
            'finalized_at' => 'datetime',
            'modification_count' => 'integer',
            'last_modification_paid_at' => 'datetime',
        ];
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent work session relationship
     */
    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    /**
     * Child work session relationship
     */
    public function childWorkSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class, 'child_work_session_id');
    }

    /**
     * Gallery relationship (NEW architecture)
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(TabloGallery::class, 'tablo_gallery_id');
    }

    /**
     * Tablo photo relationship
     */
    public function tabloPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'tablo_photo_id');
    }

    /**
     * Check if the workflow has been finalized
     */
    public function isFinalized(): bool
    {
        return $this->workflow_status === self::STATUS_FINALIZED;
    }

    /**
     * Check if the workflow can still be modified
     */
    public function canModify(): bool
    {
        return $this->workflow_status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if within free edit window (finalized + time not expired)
     */
    public function isWithinFreeEditWindow(int $freeEditHours): bool
    {
        if (!$this->isFinalized() || !$this->finalized_at) {
            return false;
        }

        return $this->finalized_at->addHours($freeEditHours)->isFuture();
    }

    /**
     * Get remaining seconds in free edit window
     */
    public function getFreeEditRemainingSeconds(int $freeEditHours): int
    {
        if (!$this->isWithinFreeEditWindow($freeEditHours)) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->finalized_at->addHours($freeEditHours), false);
    }

    /**
     * Get the retouch photos as a collection
     */
    public function getRetouchPhotos(): Collection
    {
        $ids = $this->retouch_photo_ids ?? [];

        if (empty($ids)) {
            return new Collection();
        }

        return Photo::whereIn('id', $ids)->get();
    }

    /**
     * Get the selected tablo photo
     */
    public function getTabloPhoto(): ?Photo
    {
        return $this->tabloPhoto;
    }
}
