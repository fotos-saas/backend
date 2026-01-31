<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OrphanPhoto extends Model
{
    protected $fillable = [
        'suggested_name',
        'type',
        'media_id',
        'original_filename',
        'source_info',
        'suggested_projects',
    ];

    protected $casts = [
        'source_info' => 'array',
        'suggested_projects' => 'array',
    ];

    /**
     * A kép média rekordja.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Thumbnail URL a listákhoz.
     */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->media?->getUrl('thumb');
    }

    /**
     * Teljes kép URL.
     */
    public function getFullUrlAttribute(): ?string
    {
        return $this->media?->getUrl();
    }

    /**
     * Típus magyar neve.
     */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'teacher' => 'Tanár',
            'student' => 'Diák',
            default => 'Ismeretlen',
        };
    }
}
