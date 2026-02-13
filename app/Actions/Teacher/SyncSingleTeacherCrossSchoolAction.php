<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use App\Services\Teacher\FindDonorPhotoService;

class SyncSingleTeacherCrossSchoolAction
{
    public function __construct(
        private readonly FindDonorPhotoService $donorService,
    ) {}

    /**
     * Egyetlen tanár archív fotójának szinkronizálása.
     *
     * Donor keresés:
     * 1. linked_group → teacher_photos legfrissebb year
     * 2. Fallback: canonical_name egyezés → active_photo_id
     */
    public function execute(TeacherArchive $teacher): array
    {
        if ($teacher->active_photo_id) {
            return [
                'success' => false,
                'message' => 'A tanárnak már van aktív fotója.',
            ];
        }

        $mediaId = $this->donorService->findForTeacher($teacher);

        if (!$mediaId) {
            return [
                'success' => false,
                'message' => 'Nem található szinkronizálható fotó.',
            ];
        }

        $teacher->active_photo_id = $mediaId;
        $teacher->save();

        return [
            'success' => true,
            'message' => 'Fotó sikeresen szinkronizálva.',
            'photoThumbUrl' => $teacher->photo_thumb_url,
            'photoUrl' => $teacher->photo_url,
        ];
    }
}
