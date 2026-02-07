<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * PartnerBranding Model
 *
 * Partner márkajelzés beállítások.
 * Logo, favicon és OG kép Spatie Media Library kollekcióként.
 */
class PartnerBranding extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'partner_id',
        'brand_name',
        'is_active',
        'hide_brand_name',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hide_brand_name' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brand_logo')->singleFile();
        $this->addMediaCollection('brand_favicon')->singleFile();
        $this->addMediaCollection('brand_og_image')->singleFile();
    }

    public function getLogoUrl(): ?string
    {
        return $this->getFirstMediaUrl('brand_logo') ?: null;
    }

    public function getFaviconUrl(): ?string
    {
        return $this->getFirstMediaUrl('brand_favicon') ?: null;
    }

    public function getOgImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('brand_og_image') ?: null;
    }
}
