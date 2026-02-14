<?php

namespace App\Models\Concerns;

trait HasArchivePhotos
{
    public function getPhotoMiniThumbUrlAttribute(): ?string
    {
        if (!$this->active_photo_id) {
            return null;
        }

        $media = $this->activePhoto;
        if (!$media) {
            return null;
        }

        $miniPath = $media->getPath('mini-thumb');
        if ($miniPath && file_exists($miniPath)) {
            return $media->getUrl('mini-thumb');
        }

        // Fallback: thumb ha mini-thumb még nem létezik
        return $this->photo_thumb_url;
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
