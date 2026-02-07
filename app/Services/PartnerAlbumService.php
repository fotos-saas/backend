<?php

namespace App\Services;

use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Partner album kezelő service.
 *
 * Az album-alapú képfeltöltés kezelése:
 * - Diákok és tanárok albumok
 * - Hiányzó személyek statisztika
 * - Album képek listázása és törlése
 */
class PartnerAlbumService
{
    /**
     * Támogatott album típusok
     */
    public const VALID_ALBUMS = ['students', 'teachers'];

    /**
     * Ellenőrzi, hogy érvényes album típus-e.
     */
    public function isValidAlbum(string $album): bool
    {
        return in_array($album, self::VALID_ALBUMS, true);
    }

    /**
     * Mindkét album összefoglalója.
     *
     * @param TabloProject $project Projekt
     * @return array{students: array, teachers: array}
     */
    public function getAlbumsSummary(TabloProject $project): array
    {
        $pending = $project->getMedia('tablo_pending');

        $students = $pending->filter(fn (Media $m) => $m->getCustomProperty('album') === 'students');
        $teachers = $pending->filter(fn (Media $m) => $m->getCustomProperty('album') === 'teachers');

        // Hiányzó személyek számolása (akiknek még nincs képük)
        $missingStudents = $project->persons()
            ->where('type', 'student')
            ->whereNull('media_id')
            ->count();

        $missingTeachers = $project->persons()
            ->where('type', 'teacher')
            ->whereNull('media_id')
            ->count();

        return [
            'students' => [
                'photoCount' => $students->count(),
                'missingCount' => $missingStudents,
                'firstThumbUrl' => $students->first()?->getUrl('thumb'),
                'previewThumbs' => $students->take(3)->map(fn (Media $m) => $m->getUrl('thumb'))->values()->toArray(),
            ],
            'teachers' => [
                'photoCount' => $teachers->count(),
                'missingCount' => $missingTeachers,
                'firstThumbUrl' => $teachers->first()?->getUrl('thumb'),
                'previewThumbs' => $teachers->take(3)->map(fn (Media $m) => $m->getUrl('thumb'))->values()->toArray(),
            ],
        ];
    }

    /**
     * Album képek lekérése (diákok vagy tanárok).
     *
     * @param TabloProject $project Projekt
     * @param string $album Album típus ('students' | 'teachers')
     * @return Collection<Media>
     */
    public function getAlbumPhotos(TabloProject $project, string $album): Collection
    {
        if (! $this->isValidAlbum($album)) {
            return collect();
        }

        return $project->getMedia('tablo_pending')
            ->filter(fn (Media $m) => $m->getCustomProperty('album') === $album);
    }

    /**
     * Album részletes adatai (képek + hiányzó személyek).
     *
     * @param TabloProject $project Projekt
     * @param string $album Album típus ('students' | 'teachers')
     * @return array|null Album részletek vagy null ha érvénytelen album
     */
    public function getAlbumDetails(TabloProject $project, string $album): ?array
    {
        if (! $this->isValidAlbum($album)) {
            return null;
        }

        $photos = $this->getAlbumPhotos($project, $album);

        // Típus mapping album → person type
        $personType = $album === 'students' ? 'student' : 'teacher';

        // Hiányzó személyek (akiknek még nincs képük)
        $missingPersons = $project->persons()
            ->where('type', $personType)
            ->whereNull('media_id')
            ->orderBy('position')
            ->get()
            ->map(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'email' => $person->email,
            ])
            ->values();

        return [
            'album' => $album,
            'photoCount' => $photos->count(),
            'missingCount' => $missingPersons->count(),
            'photos' => $photos->map(fn (Media $media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
                'uploadedAt' => $media->getCustomProperty('uploaded_at'),
            ])->values()->toArray(),
            'missingPersons' => $missingPersons->toArray(),
        ];
    }

    /**
     * Album összes képének törlése.
     *
     * @param TabloProject $project Projekt
     * @param string $album Album típus ('students' | 'teachers')
     * @return int Törölt képek száma
     */
    public function clearAlbum(TabloProject $project, string $album): int
    {
        if (! $this->isValidAlbum($album)) {
            return 0;
        }

        $photos = $this->getAlbumPhotos($project, $album);
        $count = $photos->count();

        foreach ($photos as $photo) {
            $photo->delete();
        }

        Log::info('PartnerAlbum: Album cleared', [
            'project_id' => $project->id,
            'album' => $album,
            'deleted_count' => $count,
        ]);

        return $count;
    }

    /**
     * Régi draft-ok migrálása album-alapúra.
     *
     * Ez egy egyszeri migráció - a régi draft_session_id-s képeket
     * students albumba teszi (mivel a legtöbb kép diák).
     *
     * @param TabloProject $project Projekt
     * @return int Migrált képek száma
     */
    public function migrateOrphanPhotos(TabloProject $project): int
    {
        $migrated = 0;

        $orphanPhotos = $project->getMedia('tablo_pending')
            ->filter(fn (Media $m) => ! $m->getCustomProperty('album'));

        foreach ($orphanPhotos as $photo) {
            // Alapértelmezetten 'students' - a legtöbb kép diák
            $photo->setCustomProperty('album', 'students');

            // Régi draft properti-k eltávolítása
            $photo->forgetCustomProperty('draft_session_id');
            $photo->forgetCustomProperty('draft_created_at');

            $photo->save();
            $migrated++;
        }

        if ($migrated > 0) {
            Log::info('PartnerAlbum: Orphan photos migrated', [
                'project_id' => $project->id,
                'migrated_count' => $migrated,
            ]);
        }

        return $migrated;
    }
}
