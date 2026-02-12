<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TeacherArchive extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'teacher_archive';

    protected $fillable = [
        'partner_id',
        'school_id',
        'canonical_name',
        'title_prefix',
        'position',
        'notes',
        'is_active',
        'active_photo_id',
        'linked_group',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('teacher_photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->nonQueued();
    }

    // ============ Relationships ============

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(TabloSchool::class, 'school_id');
    }

    public function activePhoto(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'active_photo_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(TeacherAlias::class, 'teacher_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TeacherPhoto::class, 'teacher_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(TeacherChangeLog::class, 'teacher_id');
    }

    // ============ Scopes ============

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInLinkedGroup($query, string $linkedGroup)
    {
        return $query->where('linked_group', $linkedGroup);
    }

    // ============ Helpers ============

    /**
     * Ugyanabban a linked_group-ban lévő tanárok ID-i (saját maga nélkül).
     */
    public function getLinkedTeacherIds(): array
    {
        if (!$this->linked_group) {
            return [];
        }

        return static::where('partner_id', $this->partner_id)
            ->where('linked_group', $this->linked_group)
            ->where('id', '!=', $this->id)
            ->pluck('id')
            ->toArray();
    }

    // ============ Accessors ============

    public function getFullDisplayNameAttribute(): string
    {
        if ($this->title_prefix) {
            return $this->title_prefix . ' ' . $this->canonical_name;
        }

        return $this->canonical_name;
    }

    public function getPhotoThumbUrlAttribute(): ?string
    {
        if (!$this->active_photo_id) {
            return null;
        }

        $media = $this->activePhoto;
        if (!$media) {
            return null;
        }

        $thumbPath = $media->getPath('thumb');
        if ($thumbPath && file_exists($thumbPath)) {
            return $media->getUrl('thumb');
        }

        return $media->getUrl();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->active_photo_id) {
            return null;
        }

        return $this->activePhoto?->getUrl();
    }
}
