<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;

class SyncSingleTeacherCrossSchoolAction
{
    /**
     * Egyetlen tanár archív fotójának cross-school szinkronizálása.
     *
     * Megkeresi a partner másik iskolájánál ugyanazt a canonical_name-et,
     * amelyiknek VAN active_photo_id, és átmásolja ide.
     */
    public function execute(TeacherArchive $teacher): array
    {
        if ($teacher->active_photo_id) {
            return [
                'success' => false,
                'message' => 'A tanárnak már van aktív fotója.',
            ];
        }

        // Keresés: ugyanaz a canonical_name, másik school_id, van active_photo_id
        $donor = TeacherArchive::forPartner($teacher->partner_id)
            ->active()
            ->where('canonical_name', $teacher->canonical_name)
            ->where('school_id', '!=', $teacher->school_id)
            ->whereNotNull('active_photo_id')
            ->first();

        if (! $donor) {
            return [
                'success' => false,
                'message' => 'Nem található szinkronizálható fotó más iskolából.',
            ];
        }

        $teacher->active_photo_id = $donor->active_photo_id;
        $teacher->save();

        return [
            'success' => true,
            'message' => 'Fotó sikeresen szinkronizálva.',
            'sourceSchoolId' => $donor->school_id,
            'photoThumbUrl' => $teacher->photo_thumb_url,
            'photoUrl' => $teacher->photo_url,
        ];
    }
}
