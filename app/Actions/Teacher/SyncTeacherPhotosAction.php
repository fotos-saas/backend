<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPartner;
use App\Models\TeacherArchive;
use App\Services\Teacher\FindDonorPhotoService;
use Illuminate\Support\Facades\DB;

class SyncTeacherPhotosAction
{
    public function __construct(
        private readonly FindDonorPhotoService $donorService,
    ) {}

    /**
     * Tanár fotó szinkronizálás végrehajtása — linked_group + canonical_name alapon.
     *
     * Az archívban fotó nélküli tanároknak megkeresi a donor fotót:
     * 1. linked_group → teacher_photos legfrissebb year
     * 2. Fallback: canonical_name egyezés → active_photo_id
     *
     * @param int[]|null $archiveIds Ha megadva, csak ezeket az archív tanárokat szinkronizálja
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null, ?array $archiveIds = null): array
    {
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $linkedSchoolIds)
            ->whereNull('active_photo_id')
            ->orderBy('canonical_name')
            ->get();

        if ($archives->isEmpty()) {
            return $this->emptyResult(0);
        }

        if ($archiveIds !== null) {
            $archives = $archives->whereIn('id', $archiveIds);
        }

        // Deduplikálás: linked_group VAGY canonical_name alapján
        $seenLinkedGroups = [];
        $seenNames = [];
        $uniqueArchives = collect();

        foreach ($archives as $t) {
            if ($t->linked_group) {
                if (isset($seenLinkedGroups[$t->linked_group])) {
                    continue;
                }
                $seenLinkedGroups[$t->linked_group] = true;
            } else {
                $normalizedName = mb_strtolower(trim($t->canonical_name));
                if (isset($seenNames[$normalizedName])) {
                    continue;
                }
                $seenNames[$normalizedName] = true;
            }
            $uniqueArchives->push($t);
        }

        if ($uniqueArchives->isEmpty()) {
            return $this->emptyResult(0);
        }

        $synced = 0;
        $noPhoto = 0;
        $details = [];

        DB::transaction(function () use ($uniqueArchives, &$synced, &$noPhoto, &$details) {
            foreach ($uniqueArchives as $t) {
                $mediaId = $this->donorService->findForTeacher($t);

                if (!$mediaId) {
                    $noPhoto++;
                    $details[] = [
                        'archiveId' => $t->id,
                        'personName' => $t->full_display_name,
                        'status' => 'no_photo',
                    ];
                    continue;
                }

                $t->active_photo_id = $mediaId;
                $t->save();
                $t->load('activePhoto');

                $synced++;
                $media = $t->activePhoto;
                $details[] = [
                    'archiveId' => $t->id,
                    'personName' => $t->full_display_name,
                    'status' => 'synced',
                    'photoUrl' => $t->photo_url,
                    'photoThumbUrl' => $t->photo_thumb_url,
                    'photoFileName' => $media?->file_name,
                    'photoTakenAt' => $media?->getCustomProperty('photo_taken_at'),
                ];
            }
        });

        return [
            'synced' => $synced,
            'noMatch' => 0,
            'noPhoto' => $noPhoto,
            'skipped' => 0,
            'details' => $details,
        ];
    }

    private function emptyResult(int $skipped): array
    {
        return [
            'synced' => 0,
            'noMatch' => 0,
            'noPhoto' => 0,
            'skipped' => $skipped,
            'details' => [],
        ];
    }
}
