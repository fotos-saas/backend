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
     * Linked iskolák projekttanárait is nézi, de NÉV SZERINT deduplikál:
     * ha egy tanárnév bármely projektben már rendelkezik fotóval,
     * nem jelenik meg szinkronizálhatóként.
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

        if ($teachers->isEmpty()) {
            return [
                'syncable' => 0,
                'noMatch' => 0,
                'noPhoto' => 0,
                'alreadyHasPhoto' => 0,
                'total' => 0,
                'details' => [],
            ];
        }

        // NÉV ALAPÚ deduplikálás: ha egy tanárnév bármely projektben
        // már rendelkezik media_id-vel, az egész név "already_has_photo"
        $namesWithPhoto = $teachers->whereNotNull('media_id')
            ->pluck('name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->unique()
            ->flip();

        // Deduplikált egyedi nevek → 1 person per név (első fotó nélküli példány)
        $uniqueNames = [];
        $alreadyHasPhoto = 0;
        $details = [];

        foreach ($teachers as $person) {
            $normalizedName = mb_strtolower(trim($person->name));

            // Már feldolgoztuk ezt a nevet
            if (isset($uniqueNames[$normalizedName])) {
                continue;
            }
            $uniqueNames[$normalizedName] = true;

            // Ha ez a név bármely projektben már rendelkezik fotóval → skip
            if ($namesWithPhoto->has($normalizedName)) {
                $alreadyHasPhoto++;
                $details[] = [
                    'personId' => $person->id,
                    'personName' => $person->name,
                    'status' => 'already_has_photo',
                ];
                continue;
            }

            // Fotó nélküli → matching-re kell
            // Egyelőre csak gyűjtjük, matching később batch-ben fut
        }

        // Fotó nélküli egyedi nevek: azok amelyek NEM already_has_photo
        $withoutPhotoNames = [];
        $withoutPhotoPersons = []; // név → első person (ID-nak kell)

        foreach ($teachers as $person) {
            $normalizedName = mb_strtolower(trim($person->name));

            if ($namesWithPhoto->has($normalizedName)) {
                continue; // már van fotója
            }

            if (isset($withoutPhotoNames[$normalizedName])) {
                continue; // már volt ez a név
            }

            $withoutPhotoNames[$normalizedName] = $person->name;
            $withoutPhotoPersons[$normalizedName] = $person;
        }

        $total = count($uniqueNames);

        if (empty($withoutPhotoPersons)) {
            return [
                'syncable' => 0,
                'noMatch' => 0,
                'noPhoto' => 0,
                'alreadyHasPhoto' => $alreadyHasPhoto,
                'total' => $total,
                'details' => $details,
            ];
        }

        // Matching hívás a fotó nélküli egyedi nevekre
        $names = array_values($withoutPhotoNames);
        $matchResults = $this->matchingService->matchNames($names, $partnerId, $linkedSchoolIds);
        $matchMap = collect($matchResults)->keyBy('inputName');

        $syncable = 0;
        $noMatch = 0;
        $noPhoto = 0;

        foreach ($withoutPhotoPersons as $person) {
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
