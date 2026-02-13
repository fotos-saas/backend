<?php

declare(strict_types=1);

namespace App\Services\Teacher;

use App\Models\TeacherArchive;
use App\Models\TeacherPhoto;
use Illuminate\Support\Collection;

class FindDonorPhotoService
{
    /**
     * Megkeresi a legfrissebb szinkronizálható fotót egy tanárhoz.
     *
     * Prioritás:
     * 1. linked_group → teacher_photos legfrissebb (year DESC, is_active DESC, created_at DESC)
     * 2. Fallback: canonical_name egyezés → active_photo_id
     */
    public function findForTeacher(TeacherArchive $teacher): ?int
    {
        // 1. Linked group keresés a teacher_photos táblából
        if ($teacher->linked_group) {
            $photo = TeacherPhoto::query()
                ->whereHas('teacher', fn ($q) => $q
                    ->where('partner_id', $teacher->partner_id)
                    ->where('linked_group', $teacher->linked_group)
                    ->where('id', '!=', $teacher->id)
                )
                ->orderByDesc('year')
                ->orderByDesc('is_active')
                ->orderByDesc('created_at')
                ->first();

            if ($photo) {
                return $photo->media_id;
            }
        }

        // 2. Fallback: canonical_name + más rekord
        $donor = TeacherArchive::forPartner($teacher->partner_id)
            ->active()
            ->where('canonical_name', $teacher->canonical_name)
            ->where('id', '!=', $teacher->id)
            ->whereNotNull('active_photo_id')
            ->first();

        return $donor?->active_photo_id;
    }

    /**
     * Batch: mely linked_group UUID-kben van teacher_photos rekord?
     * Visszaadja azokat a linked_group-okat ahol VAN fotó.
     *
     * @param Collection<int, TeacherArchive> $teachers
     * @return Collection<string, true> Set (flip-elt) a linked_group UUID-kkel
     */
    public function getLinkedGroupsWithPhotos(int $partnerId, Collection $teachers): Collection
    {
        $linkedGroups = $teachers
            ->pluck('linked_group')
            ->filter()
            ->unique()
            ->values();

        if ($linkedGroups->isEmpty()) {
            return collect();
        }

        // Egy query: melyik linked_group-ban van teacher_photos rekord
        return TeacherPhoto::query()
            ->whereHas('teacher', fn ($q) => $q
                ->where('partner_id', $partnerId)
                ->whereIn('linked_group', $linkedGroups->toArray())
            )
            ->join('teacher_archive', 'teacher_photos.teacher_id', '=', 'teacher_archive.id')
            ->distinct()
            ->pluck('teacher_archive.linked_group')
            ->filter()
            ->flip();
    }
}
