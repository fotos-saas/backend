<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PhotoVersion extends Model
{
    protected $fillable = [
        'photo_id',
        'media_id',
        'path',
        'original_filename',
        'reason',
        'replaced_by_user_id',
        'is_restored',
        'width',
        'height',
    ];

    protected $casts = [
        'is_restored' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * Get the photo that this version belongs to
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * Get the user who replaced this version
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by_user_id');
    }

    /**
     * Get the original media that was replaced (via media_id)
     */
    public function originalMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Get the version media from photo_version_{id} collection
     */
    public function media(): ?Media
    {
        $collectionName = 'photo_version_'.$this->id;

        return $this->photo
            ? $this->photo->getFirstMedia($collectionName)
            : null;
    }

    /**
     * Get the original filename from version media or fallback to stored value
     */
    public function getOriginalFilenameAttribute(): ?string
    {
        $versionMedia = $this->media();
        
        if ($versionMedia) {
            return $versionMedia->getCustomProperty('original_filename') ?? $versionMedia->file_name;
        }
        
        return $this->attributes['original_filename'] ?? null;
    }

    /**
     * Get thumbnail URL (300x300)
     */
    public function getThumbUrl(): ?string
    {
        $media = $this->media();

        return $media?->getUrl('thumb');
    }

    /**
     * Get preview URL (1200x1200)
     */
    public function getPreviewUrl(): ?string
    {
        $media = $this->media();

        return $media?->getUrl('preview');
    }
}
