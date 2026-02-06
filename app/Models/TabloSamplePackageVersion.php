<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloSamplePackageVersion extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'package_id',
        'version_number',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TabloSamplePackage::class, 'package_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('sample_image')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * Összes kép adatai (multi-image)
     */
    public function getImagesAttribute(): array
    {
        return $this->getMedia('sample_image')->map(fn (Media $m) => [
            'id' => $m->id,
            'url' => $m->getUrl(),
            'thumbUrl' => $m->getUrl('thumb'),
        ])->toArray();
    }
}
