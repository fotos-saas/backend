<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPartner;
use App\Models\TeacherArchive;
use App\Services\Teacher\FindDonorPhotoService;

class PreviewTeacherSyncAction
{
    public function __construct(
        private readonly FindDonorPhotoService $donorService,
    ) {}

    /**
     * Tanár fotó szinkronizálás előnézet — linked_group + canonical_name alapon.
     *
     * FONTOS: NEM deduplikálunk — ha ugyanaz a tanár több iskolánál is
     * szerepel (linked_group), mindegyiket megmutatjuk az előnézetben.
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null): array
    {
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $linkedSchoolIds)
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        if ($archives->isEmpty()) {
            return $this->emptyResult();
        }

        $details = [];
        $syncable = 0;
        $noPhoto = 0;
        $alreadyHasPhoto = 0;

        foreach ($archives as $t) {
            if ($t->active_photo_id !== null) {
                $alreadyHasPhoto++;
                continue;
            }

            $mediaId = $this->donorService->findForTeacher($t);

            if ($mediaId) {
                $syncable++;
                $details[] = [
                    'archiveId' => $t->id,
                    'personName' => $t->full_display_name,
                    'status' => 'syncable',
                    'matchType' => $t->linked_group ? 'linked_group' : 'exact',
                    'confidence' => 1.0,
                ];
            } else {
                $noPhoto++;
                $details[] = [
                    'archiveId' => $t->id,
                    'personName' => $t->full_display_name,
                    'status' => 'no_photo',
                ];
            }
        }

        return [
            'syncable' => $syncable,
            'noMatch' => 0,
            'noPhoto' => $noPhoto,
            'alreadyHasPhoto' => $alreadyHasPhoto,
            'total' => $archives->count(),
            'details' => $details,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'syncable' => 0,
            'noMatch' => 0,
            'noPhoto' => 0,
            'alreadyHasPhoto' => 0,
            'total' => 0,
            'details' => [],
        ];
    }
}
