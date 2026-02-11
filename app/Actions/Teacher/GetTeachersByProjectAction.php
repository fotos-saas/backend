<?php

namespace App\Actions\Teacher;

use App\Helpers\QueryHelper;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use Illuminate\Support\Collection;

class GetTeachersByProjectAction
{
    /**
     * Iskolánként csoportosított tanárok lekérdezése.
     *
     * Logika: projektek school_id → iskolánként egyedi tanárok az archive-ból.
     * Az osztályok kontextusnak jelennek meg, de a tanár lista deduplikált.
     * Így ha egy iskolának 5 osztálya van, a tanárok egyszer jelennek meg.
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

        // Csoportosítás school_id szerint
        $archivesBySchool = $archives->groupBy('school_id');

        // Cross-school sync: partner összes tanárneve akihez VAN fotó (bármely iskolánál)
        $allNamesWithPhoto = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereNotNull('active_photo_id')
            ->pluck('canonical_name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->unique()
            ->flip();

        // Iskolánkénti csoportosítás: projektek → iskolák
        $projectsBySchool = $projects->groupBy('school_id');

        // Összegyűjtjük az összes iskola adatát (summary-hoz is kell a teljes kép)
        $allSchools = collect($schoolIds)->map(function (int $sid) use ($projectsBySchool, $archivesBySchool, $teacherPersonSchoolIds, $allNamesWithPhoto) {
            $schoolProjects = $projectsBySchool->get($sid, collect());
            $schoolTeachers = $archivesBySchool->get($sid, collect());

            $school = $schoolProjects->first()?->school;
            if (! $school) {
                return null;
            }

            $teachers = $schoolTeachers->map(fn (TeacherArchive $t) => [
                'archiveId' => $t->id,
                'name' => $t->full_display_name,
                'hasPhoto' => $t->photo_thumb_url !== null,
                'noPhotoMarked' => $t->notes && str_contains($t->notes, 'Nem találom a képet'),
                'photoThumbUrl' => $t->photo_thumb_url,
                'photoUrl' => $t->photo_url,
            ]);

            $totalCount = $teachers->count();
            $missingCount = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->count();

            // Sync elérhető: van-e hiányzó tanár akihez a partner bármely iskolájában VAN fotó
            $syncAvailable = $missingCount > 0 && $schoolTeachers
                ->filter(fn (TeacherArchive $t) => $t->photo_thumb_url === null)
                ->contains(fn (TeacherArchive $t) => $allNamesWithPhoto->has(mb_strtolower(trim($t->canonical_name))));

            // Osztályok listája kontextusnak
            $classes = $schoolProjects->map(fn (TabloProject $p) => [
                'projectId' => $p->id,
                'className' => $p->class_name,
                'classYear' => $p->class_year,
            ])->values()->toArray();

            return [
                'schoolId' => $sid,
                'schoolName' => $school->name,
                'classes' => $classes,
                'classCount' => count($classes),
                'teacherCount' => $totalCount,
                'missingPhotoCount' => $missingCount,
                'hasTeacherPersons' => in_array($sid, $teacherPersonSchoolIds, true),
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
            })->filter()->sortByDesc('missingPhotoCount')->values();
        } else {
            $displaySchools = $allSchools->sortByDesc('missingPhotoCount')->values();
        }

        return [
            'schools' => $displaySchools->toArray(),
            'summary' => $summary,
        ];
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
