<?php

namespace App\Services;

use App\Models\Album;
use Illuminate\Support\Collection;

class AlbumSchoolClassService
{
    /**
     * Get school classes for an album (eager loaded)
     */
    public function getSchoolClassesForAlbum(int $albumId): Collection
    {
        $album = Album::with('schoolClasses')->findOrFail($albumId);
        return $album->schoolClasses;
    }

    /**
     * Check if school class is valid for album
     */
    public function isSchoolClassValidForAlbum(Album $album, int $schoolClassId): bool
    {
        return $album->schoolClasses->contains('id', $schoolClassId);
    }

    /**
     * Sync school classes for an album
     */
    public function syncSchoolClasses(Album $album, array $schoolClassIds): void
    {
        $album->schoolClasses()->sync($schoolClassIds);
    }
}
