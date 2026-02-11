<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPartner;
use App\Models\TeacherArchive;
use Illuminate\Support\Facades\DB;

class PreviewTeacherSyncAction
{
    /**
     * Tanár fotó szinkronizálás előnézet — archív cross-school alapon.
     *
     * Ugyanazt a logikát követi mint a GetTeachersByProjectAction:
     * az archívban fotó nélküli tanárokat nézi, akiknek MÁS iskolánál
     * VAN fotójuk (cross-school sync).
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null): array
    {
        // Összekapcsolt iskolák feloldása
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        // Archív tanárok az összekapcsolt iskolákra
        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $linkedSchoolIds)
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        if ($archives->isEmpty()) {
            return $this->emptyResult();
        }

        // Cross-school: partner összes tanárneve akihez VAN fotó (bármely iskolánál)
        $allNamesWithPhoto = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereNotNull('active_photo_id')
            ->pluck('canonical_name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->unique()
            ->flip();

        // NÉV alapú deduplikálás (linked iskolák közös tanárai)
        $seenNames = [];
        $details = [];
        $syncable = 0;
        $noPhoto = 0;
        $alreadyHasPhoto = 0;

        foreach ($archives as $t) {
            $normalizedName = mb_strtolower(trim($t->canonical_name));
            if (isset($seenNames[$normalizedName])) {
                continue;
            }
            $seenNames[$normalizedName] = true;

            $archiveHasPhoto = $t->active_photo_id !== null;

            if ($archiveHasPhoto) {
                $alreadyHasPhoto++;
                continue;
            }

            // Nincs archív fotó — van-e másik iskolánál?
            if ($allNamesWithPhoto->has($normalizedName)) {
                // Donor keresése: ugyanaz a canonical_name, másik school_id, van active_photo_id
                $donor = TeacherArchive::forPartner($partnerId)
                    ->active()
                    ->where('canonical_name', $t->canonical_name)
                    ->whereNotIn('school_id', $linkedSchoolIds)
                    ->whereNotNull('active_photo_id')
                    ->with('activePhoto')
                    ->first();

                if ($donor) {
                    $syncable++;
                    $details[] = [
                        'archiveId' => $t->id,
                        'personName' => $t->full_display_name,
                        'status' => 'syncable',
                        'matchType' => 'exact',
                        'teacherName' => $donor->full_display_name,
                        'teacherId' => $donor->id,
                        'confidence' => 1.0,
                        'photoThumbUrl' => $donor->photo_thumb_url,
                    ];
                } else {
                    $noPhoto++;
                    $details[] = [
                        'archiveId' => $t->id,
                        'personName' => $t->full_display_name,
                        'status' => 'no_photo',
                    ];
                }
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
            'total' => count($seenNames),
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
