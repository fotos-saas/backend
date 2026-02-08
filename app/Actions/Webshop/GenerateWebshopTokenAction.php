<?php

declare(strict_types=1);

namespace App\Actions\Webshop;

use App\Models\PartnerAlbum;
use App\Models\TabloGallery;
use Illuminate\Support\Str;

class GenerateWebshopTokenAction
{
    public function execute(int $partnerId, ?int $albumId = null, ?int $galleryId = null): string
    {
        $token = Str::random(32);

        if ($albumId) {
            $album = PartnerAlbum::byPartner($partnerId)->findOrFail($albumId);
            $album->update(['webshop_share_token' => $token]);

            return $token;
        }

        if ($galleryId) {
            $gallery = TabloGallery::whereHas('projects', function ($q) use ($partnerId) {
                $q->where('tablo_partner_id', $partnerId);
            })->findOrFail($galleryId);

            $gallery->update(['webshop_share_token' => $token]);

            return $token;
        }

        throw new \InvalidArgumentException('album_id vagy gallery_id kötelező.');
    }
}
