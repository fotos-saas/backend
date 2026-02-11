<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPerson;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Services\Teacher\TeacherMatchingService;

class PreviewTeacherSyncAction
{
    public function __construct(
        private readonly TeacherMatchingService $matchingService,
    ) {}

    /**
     * Tanár fotó szinkronizálás előnézet — nem ír DB-be.
     * Egy iskola ÖSSZES projektjére vonatkozik.
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null): array
    {
        // Összekapcsolt iskolák feloldása
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        $query = TabloProject::where('partner_id', $partnerId)
            ->whereIn('school_id', $linkedSchoolIds);

        if ($classYear) {
            $query->where('class_year', $classYear);
        }

        $projectIds = $query->pluck('id');

        if ($projectIds->isEmpty()) {
            return [
                'syncable' => 0,
                'noMatch' => 0,
                'noPhoto' => 0,
                'alreadyHasPhoto' => 0,
                'total' => 0,
                'details' => [],
            ];
        }

        // Összes tanár típusú személy az iskola projektjeiben
        $teachers = TabloPerson::whereIn('tablo_project_id', $projectIds)
            ->where('type', 'teacher')
            ->get();

        $total = $teachers->count();

        if ($total === 0) {
            return [
                'syncable' => 0,
                'noMatch' => 0,
                'noPhoto' => 0,
                'alreadyHasPhoto' => 0,
                'total' => 0,
                'details' => [],
            ];
        }

        // Már van fotója → skip
        $withPhoto = $teachers->whereNotNull('media_id');
        $withoutPhoto = $teachers->whereNull('media_id');

        $alreadyHasPhoto = $withPhoto->count();

        if ($withoutPhoto->isEmpty()) {
            return [
                'syncable' => 0,
                'noMatch' => 0,
                'noPhoto' => 0,
                'alreadyHasPhoto' => $alreadyHasPhoto,
                'total' => $total,
                'details' => $withPhoto->map(fn (TabloPerson $p) => [
                    'personId' => $p->id,
                    'personName' => $p->name,
                    'status' => 'already_has_photo',
                ])->values()->toArray(),
            ];
        }

        // Matching hívás a fotó nélküli tanárokra
        $names = $withoutPhoto->pluck('name')->unique()->values()->toArray();
        $matchResults = $this->matchingService->matchNames($names, $partnerId, $linkedSchoolIds);

        // Match eredmények indexelése input név alapján
        $matchMap = collect($matchResults)->keyBy('inputName');

        $syncable = 0;
        $noMatch = 0;
        $noPhoto = 0;
        $details = [];

        foreach ($withoutPhoto as $person) {
            $match = $matchMap->get($person->name);

            if (!$match || $match['matchType'] === 'no_match') {
                $noMatch++;
                $details[] = [
                    'personId' => $person->id,
                    'personName' => $person->name,
                    'status' => 'no_match',
                ];
                continue;
            }

            // Van match — de van-e fotó az archívban?
            if (!$match['photoUrl']) {
                $noPhoto++;
                $details[] = [
                    'personId' => $person->id,
                    'personName' => $person->name,
                    'status' => 'no_photo',
                    'matchType' => $match['matchType'],
                    'teacherName' => $match['teacherName'],
                    'confidence' => $match['confidence'],
                ];
                continue;
            }

            $syncable++;
            $details[] = [
                'personId' => $person->id,
                'personName' => $person->name,
                'status' => 'syncable',
                'matchType' => $match['matchType'],
                'teacherName' => $match['teacherName'],
                'teacherId' => $match['teacherId'],
                'confidence' => $match['confidence'],
                'photoThumbUrl' => $match['photoUrl'],
            ];
        }

        // Már fotós tanárok is belekerülnek a details-be
        foreach ($withPhoto as $person) {
            $details[] = [
                'personId' => $person->id,
                'personName' => $person->name,
                'status' => 'already_has_photo',
            ];
        }

        return [
            'syncable' => $syncable,
            'noMatch' => $noMatch,
            'noPhoto' => $noPhoto,
            'alreadyHasPhoto' => $alreadyHasPhoto,
            'total' => $total,
            'details' => $details,
        ];
    }
}
