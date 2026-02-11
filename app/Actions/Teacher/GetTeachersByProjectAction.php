<?php

namespace App\Actions\Teacher;

use App\Helpers\QueryHelper;
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
            return $this->buildResponse(collect());
        }

        // Batch load: partner összes aktív archive rekordja a releváns iskolákra
        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $schoolIds)
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        // Csoportosítás school_id szerint
        $archivesBySchool = $archives->groupBy('school_id');

        // Iskolánkénti csoportosítás: projektek → iskolák
        $projectsBySchool = $projects->groupBy('school_id');

        $result = collect($schoolIds)->map(function (int $sid) use ($projectsBySchool, $archivesBySchool, $missingOnly) {
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
                'photoThumbUrl' => $t->photo_thumb_url,
                'photoUrl' => $t->photo_url,
            ]);

            $totalCount = $teachers->count();
            $missingCount = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->count();

            if ($missingOnly) {
                $teachers = $teachers->filter(fn ($t) => ! $t['hasPhoto']);
            }

            if ($missingOnly && $teachers->isEmpty()) {
                return null;
            }

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
                'teachers' => $teachers->values()->toArray(),
            ];
        })
            ->filter()
            ->sortByDesc('missingPhotoCount')
            ->values();

        return $this->buildResponse($result);
    }

    private function buildResponse(Collection $schools): array
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
            'schools' => $schools->toArray(),
            'summary' => [
                'totalSchools' => $schools->count(),
                'totalTeachers' => $totalTeachers,
                'withPhoto' => $withPhoto,
                'missingPhoto' => $missingPhoto,
            ],
        ];
    }
}
