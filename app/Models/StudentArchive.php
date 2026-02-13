<?php

namespace App\Models;

use App\Models\Concerns\HasArchivePhotos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StudentArchive extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasArchivePhotos;

    protected $table = 'student_archive';

    protected $fillable = [
        'partner_id',
        'school_id',
        'canonical_name',
        'class_name',
        'notes',
        'is_active',
        'active_photo_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('student_photos');
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
        return $this->hasMany(StudentAlias::class, 'student_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(StudentPhoto::class, 'student_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(StudentChangeLog::class, 'student_id');
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
}
