<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGallery;
use App\Models\TabloProject;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Galéria létrehozása vagy lekérdezése egy projekthez.
 * Tartalmazza a galéria formázás és névgenerálás logikáját.
 */
class CreateGalleryAction
{
    /**
     * Galéria létrehozása vagy meglévő visszaadása.
     *
     * @return array{success: bool, created: bool, gallery: array, deadline: ?string}
     */
    public function execute(TabloProject $project): array
    {
        if ($project->tablo_gallery_id) {
            return [
                'success' => true,
                'created' => false,
                'gallery' => $this->formatGallery($project->gallery),
                'deadline' => $project->deadline?->toDateString(),
            ];
        }

        $galleryName = $this->buildGalleryName($project);

        $gallery = TabloGallery::create([
            'name' => $galleryName,
            'status' => 'active',
            'max_retouch_photos' => 3,
        ]);

        $project->update(['tablo_gallery_id' => $gallery->id]);

        return [
            'success' => true,
            'created' => true,
            'gallery' => $this->formatGallery($gallery),
            'deadline' => $project->deadline?->toDateString(),
        ];
    }

    /**
     * Galéria adatok formázása API válaszhoz.
     */
    public function formatGallery(TabloGallery $gallery): array
    {
        $photos = $gallery->getMedia('photos');
        $totalSize = $photos->sum('size');

        return [
            'id' => $gallery->id,
            'name' => $gallery->name,
            'photosCount' => $photos->count(),
            'totalSizeMb' => round($totalSize / 1024 / 1024, 2),
            'maxRetouchPhotos' => $gallery->max_retouch_photos,
            'createdAt' => $gallery->created_at->toIso8601String(),
            'photos' => $photos->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'title' => $media->getCustomProperty('iptc_title', ''),
                'thumb_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'original_url' => $media->getUrl(),
                'size' => $media->size,
                'createdAt' => $media->created_at->toIso8601String(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Galéria név összeállítása a projekt adataiból.
     */
    private function buildGalleryName(TabloProject $project): string
    {
        $parts = [];

        if ($project->school) {
            $parts[] = $project->school->name;
        }

        if ($project->class_name) {
            $parts[] = $project->class_name;
        }

        return implode(' - ', $parts) ?: 'Galéria';
    }
}
