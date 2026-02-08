<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloGallery extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'status',
        'max_retouch_photos',
        'webshop_share_token',
    ];

    protected $casts = [
        'max_retouch_photos' => 'integer',
    ];

    /**
     * Get the projects associated with this gallery.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(TabloProject::class, 'tablo_gallery_id');
    }

    /**
     * Register media collections for photos.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');
    }

    /**
     * Register media conversions (thumbnails and previews).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(1200)
            ->height(1200)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * Scope to filter only active galleries.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter only archived galleries.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Get the photos count attribute.
     */
    public function getPhotosCountAttribute(): int
    {
        return $this->getMedia('photos')->count();
    }
}
