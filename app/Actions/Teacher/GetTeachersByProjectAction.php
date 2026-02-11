<?php

namespace App\Actions\Teacher;

use App\Helpers\QueryHelper;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTeachersByProjectAction
{
    /**
     * Iskolánként csoportosított tanárok lekérdezése.
     *
     * Logika: projektek school_id → iskolánként egyedi tanárok az archive-ból.
     * Összekapcsolt iskolák (linked_group) egy csoportba kerülnek,
     * a legrövidebb iskolanévvel.
     */
    public function execute(int $partnerId, ?string $classYear = null, ?int $schoolId = null, bool $missingOnly = false): array
    {
        $query = TabloProject::where('partner_id', $partnerId)
            ->with('school')
            ->whereNotNull('school_id');

        if ($classYear) {
            $query->where('class_year', 'ILIKE', QueryHelper::safeLikePattern($classYear));
        }
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $query->orderByDesc('class_year')->orderBy('class_name');
        $projects = $query->get();

        // Releváns school_id-k
        $schoolIds = $projects->pluck('school_id')->filter()->unique()->values()->toArray();

        if (empty($schoolIds)) {
            return [
                'schools' => [],
                'summary' => $this->buildSummary(collect()),
            ];
        }

        // Linked group térkép: school_id → group_key
        // Ha egy school_id linked_group-ban van, a csoport összes school_id-ja
        // ugyanazt a group_key-t kapja (legkisebb school_id a csoportban)
        $linkedMap = $this->buildLinkedMap($partnerId, $schoolIds);

        // Batch load: van-e tanár típusú személy az egyes iskolák projektjeiben
        $allProjectIds = $projects->pluck('id')->toArray();
        $teacherPersonSchoolIds = TabloPerson::whereIn('tablo_project_id', $allProjectIds)
            ->where('type', 'teacher')
            ->join('tablo_projects', 'tablo_persons.tablo_project_id', '=', 'tablo_projects.id')
            ->distinct()
            ->pluck('tablo_projects.school_id')
            ->toArray();

        // Batch load: partner összes aktív archive rekordja a releváns iskolákra
        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $schoolIds)
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        // Cross-school sync: partner összes tanárneve akihez VAN fotó (bármely iskolánál)
        $allNamesWithPhoto = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereNotNull('active_photo_id')
            ->pluck('canonical_name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->unique()
            ->flip();

        // Projektek és archívok csoportosítása linked group szerint
        $projectsByGroup = collect();
        $archivesByGroup = collect();
        $schoolNamesByGroup = collect();

        foreach ($schoolIds as $sid) {
            $groupKey = $linkedMap[$sid] ?? $sid;

            // Projektek összegyűjtése csoport szerint
            $sidProjects = $projects->where('school_id', $sid);
            if (!$projectsByGroup->has($groupKey)) {
                $projectsByGroup[$groupKey] = collect();
            }
            $projectsByGroup[$groupKey] = $projectsByGroup[$groupKey]->merge($sidProjects);

            // Archívok összegyűjtése csoport szerint
            $sidArchives = $archives->where('school_id', $sid);
            if (!$archivesByGroup->has($groupKey)) {
                $archivesByGroup[$groupKey] = collect();
            }
            $archivesByGroup[$groupKey] = $archivesByGroup[$groupKey]->merge($sidArchives);

            // Iskolanév: a legrövidebb nevet választjuk
            $school = $sidProjects->first()?->school;
            if ($school) {
                $currentName = $schoolNamesByGroup->get($groupKey);
                if ($currentName === null || mb_strlen($school->name) < mb_strlen($currentName)) {
                    $schoolNamesByGroup[$groupKey] = $school->name;
                }
            }
        }

        // Egyedi csoport kulcsok
        $groupKeys = $projectsByGroup->keys();

        $allSchools = $groupKeys->map(function ($groupKey) use (
            $projectsByGroup, $archivesByGroup, $schoolNamesByGroup,
            $teacherPersonSchoolIds, $allNamesWithPhoto, $linkedMap
        ) {
            $groupProjects = $projectsByGroup->get($groupKey, collect());
            $groupArchives = $archivesByGroup->get($groupKey, collect());
            $schoolName = $schoolNamesByGroup->get($groupKey);

            if (!$schoolName || $groupProjects->isEmpty()) {
                return null;
            }

            // Csoport school_id-k meghatározása (linked iskolák)
            $groupSchoolIds = collect();
            foreach ($linkedMap as $sid => $gk) {
                if ($gk === $groupKey) {
                    $groupSchoolIds->push($sid);
                }
            }
            if ($groupSchoolIds->isEmpty()) {
                $groupSchoolIds->push($groupKey);
            }

            // Tanárok deduplikálás NÉV alapján (linked iskolák közös tanárai)
            $seenNames = [];
            $teachers = collect();
            foreach ($groupArchives->sortBy('canonical_name') as $t) {
                $normalizedName = mb_strtolower(trim($t->canonical_name));
                if (isset($seenNames[$normalizedName])) {
                    continue;
                }
                $seenNames[$normalizedName] = true;

                $teachers->push([
                    'archiveId' => $t->id,
                    'name' => $t->full_display_name,
                    'hasPhoto' => $t->photo_thumb_url !== null,
                    'hasSyncablePhoto' => $t->photo_thumb_url === null && $allNamesWithPhoto->has($normalizedName),
                    'noPhotoMarked' => $t->notes && str_contains($t->notes, 'Nem találom a képet'),
                    'photoThumbUrl' => $t->photo_thumb_url,
                    'photoUrl' => $t->photo_url,
                ]);
            }

            // Rendezés: hiányzó fotó felül (syncable előbb), aztán ABC
            $teachers = $teachers->sort(function ($a, $b) {
                // 1. Nincs fotó → felül
                if ($a['hasPhoto'] !== $b['hasPhoto']) {
                    return $a['hasPhoto'] <=> $b['hasPhoto'];
                }
                // 2. Hiányzók között: syncable előbb
                if (!$a['hasPhoto'] && !$b['hasPhoto']) {
                    if ($a['hasSyncablePhoto'] !== $b['hasSyncablePhoto']) {
                        return $b['hasSyncablePhoto'] <=> $a['hasSyncablePhoto'];
                    }
                }
                // 3. ABC sorrend
                return strcmp($a['name'], $b['name']);
            });

            $totalCount = $teachers->count();
            $missingCount = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->count();

            // Sync elérhető: van-e szinkronizálható tanár (archív cross-school VAGY projekt sync)
            $syncAvailable = $teachers->contains(fn ($t) => $t['hasSyncablePhoto']);

            $hasTeacherPersons = $groupSchoolIds->contains(fn ($sid) =>
                in_array($sid, $teacherPersonSchoolIds, true)
            );

            // Osztályok listája kontextusnak
            $classes = $groupProjects->map(fn (TabloProject $p) => [
                'projectId' => $p->id,
                'className' => $p->class_name,
                'classYear' => $p->class_year,
            ])->values()->toArray();

            return [
                'schoolId' => (int) $groupKey,
                'schoolName' => $schoolName,
                'classes' => $classes,
                'classCount' => count($classes),
                'teacherCount' => $totalCount,
                'missingPhotoCount' => $missingCount,
                'hasTeacherPersons' => $hasTeacherPersons,
                'syncAvailable' => $syncAvailable,
                'teachers' => $teachers->values()->toArray(),
            ];
        })->filter()->values();

        // Summary MINDIG a teljes (szűretlen) adatból
        $summary = $this->buildSummary($allSchools);

        // missingOnly szűrés csak a megjelenített listára
        $displaySchools = $allSchools;
        if ($missingOnly) {
            $displaySchools = $allSchools->map(function (array $school) {
                $filtered = collect($school['teachers'])->filter(fn ($t) => ! $t['hasPhoto'])->values()->toArray();
                if (empty($filtered)) {
                    return null;
                }
                $school['teachers'] = $filtered;
                return $school;
            })->filter();
        }

        // Rendezés: syncAvailable iskolák felül, aztán missingPhotoCount csökkenő
        $displaySchools = $displaySchools->sort(function (array $a, array $b) {
            if ($a['syncAvailable'] !== $b['syncAvailable']) {
                return $b['syncAvailable'] <=> $a['syncAvailable'];
            }

            return $b['missingPhotoCount'] <=> $a['missingPhotoCount'];
        })->values();

        return [
            'schools' => $displaySchools->toArray(),
            'summary' => $summary,
        ];
    }

    /**
     * Linked group térkép felépítése: school_id → group_key.
     * A group_key a csoport legkisebb school_id-ja.
     */
    private function buildLinkedMap(int $partnerId, array $schoolIds): array
    {
        $links = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('linked_group')
            ->get(['school_id', 'linked_group']);

        if ($links->isEmpty()) {
            // Nincs linking — minden school_id önálló
            return array_combine($schoolIds, $schoolIds);
        }

        // linked_group → school_id-k
        $groups = $links->groupBy('linked_group');

        $map = [];
        foreach ($schoolIds as $sid) {
            $map[$sid] = $sid; // default: önálló
        }

        foreach ($groups as $groupName => $members) {
            $memberIds = $members->pluck('school_id')->toArray();
            // A csoport kulcsa a legkisebb school_id
            $groupKey = min($memberIds);
            foreach ($memberIds as $mid) {
                $map[$mid] = $groupKey;
            }
        }

        return $map;
    }

    private function buildSummary(Collection $schools): array
    {
        $totalTeachers = 0;
        $withPhoto = 0;
        $missingPhoto = 0;

        foreach ($schools as $s) {
            $totalTeachers += $s['teacherCount'];
            $missing = $s['missingPhotoCount'];
            $missingPhoto += $missing;
            $withPhoto += $s['teacherCount'] - $missing;
        }

        return [
            'totalSchools' => $schools->count(),
            'totalTeachers' => $totalTeachers,
            'withPhoto' => $withPhoto,
            'missingPhoto' => $missingPhoto,
        ];
    }
}
