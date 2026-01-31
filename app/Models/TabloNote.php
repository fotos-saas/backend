<?php

namespace App\Models;

use App\Enums\NoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabloNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'content',
        'user_id',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'status' => NoteStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get the author
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get author name
     */
    public function getAuthorNameAttribute(): string
    {
        return $this->user?->name ?? 'Ismeretlen';
    }

    /**
     * Get the user who resolved the note
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Mark note as resolved
     */
    public function markAsResolved(NoteStatus $status, ?int $userId = null): void
    {
        $this->update([
            'status' => $status,
            'resolved_by' => $userId ?? auth()->id(),
            'resolved_at' => now(),
        ]);
    }
}
