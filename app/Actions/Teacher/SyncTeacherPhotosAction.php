<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPerson;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use App\Services\Teacher\TeacherMatchingService;
use Illuminate\Support\Facades\DB;

class SyncTeacherPhotosAction
{
    public function __construct(
        private readonly TeacherMatchingService $matchingService,
    ) {}

    /**
     * Tanár fotó szinkronizálás végrehajtása — archív fotó hozzárendelés.
     * Linked iskolák projekttanárait is nézi, NÉV SZERINT deduplikál.
     * Ha person_ids megadva, csak azokat szinkronizálja.
     *
     * @param int[]|null $personIds Ha megadva, csak ezeket a person-öket szinkronizálja
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null, ?array $personIds = null): array
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
            return $this->emptyResult(0);
        }

        $teachers = TabloPerson::whereIn('tablo_project_id', $projectIds)
            ->where('type', 'teacher')
            ->get();

        if ($teachers->isEmpty()) {
            return $this->emptyResult(0);
        }

        // NÉV ALAPÚ deduplikálás: ha egy tanárnév bármely projektben
        // már rendelkezik media_id-vel, azt kihagyjuk
        $namesWithPhoto = $teachers->whereNotNull('media_id')
            ->pluck('name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->unique()
            ->flip();

        // Fotó nélküli person-ök, akiknek a neve SEHOL nincs fotóval
        $withoutPhoto = $teachers->filter(function (TabloPerson $p) use ($namesWithPhoto) {
            if ($p->media_id !== null) {
                return false;
            }
            return !$namesWithPhoto->has(mb_strtolower(trim($p->name)));
        });

        // Ha person_ids megadva, csak azokat szinkronizáljuk
        if ($personIds !== null) {
            $withoutPhoto = $withoutPhoto->whereIn('id', $personIds);
        }

        $skipped = $teachers->count() - $withoutPhoto->count();

        if ($withoutPhoto->isEmpty()) {
            return $this->emptyResult($skipped);
        }

        $names = $withoutPhoto->pluck('name')->unique()->values()->toArray();
        $matchResults = $this->matchingService->matchNames($names, $partnerId, $linkedSchoolIds);
        $matchMap = collect($matchResults)->keyBy('inputName');

        $synced = 0;
        $noMatch = 0;
        $noPhoto = 0;
        $details = [];

        DB::transaction(function () use ($withoutPhoto, $matchMap, &$synced, &$noMatch, &$noPhoto, &$details) {
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

                // Van match — archív tanár active_photo_id lekérése
                $archive = TeacherArchive::find($match['teacherId']);
                if (!$archive || !$archive->active_photo_id) {
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

                // Szinkronizálás: media_id átállítás
                $person->media_id = $archive->active_photo_id;
                $person->save();

                $synced++;
                $details[] = [
                    'personId' => $person->id,
                    'personName' => $person->name,
                    'status' => 'synced',
                    'matchType' => $match['matchType'],
                    'teacherName' => $match['teacherName'],
                    'confidence' => $match['confidence'],
                ];
            }
        });

        return [
            'synced' => $synced,
            'noMatch' => $noMatch,
            'noPhoto' => $noPhoto,
            'skipped' => $skipped,
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
