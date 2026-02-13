<?php

namespace App\Actions\Student;

use App\Models\StudentArchive;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use Illuminate\Support\Collection;

class GetStudentsByProjectAction
{
    public function execute(int $partnerId, ?string $classYear = null, ?int $schoolId = null, bool $missingOnly = false): array
    {
        // Archive-based: iskolák lekérése a StudentArchive táblából (nem TabloProject-ből!)
        $archiveQuery = StudentArchive::forPartner($partnerId)->active();
        if ($schoolId) {
            $archiveQuery->where('school_id', $schoolId);
        }

        $schoolIds = $archiveQuery->distinct()->pluck('school_id')->filter()->toArray();

        if (empty($schoolIds)) {
            return [
                'schools' => [],
                'summary' => $this->buildSummary(collect()),
            ];
        }

        // Iskola adatok lekérése
        $schools = TabloSchool::whereIn('id', $schoolIds)->get()->keyBy('id');

        // Projektek lekérése (osztály kontextushoz)
        $projectQuery = TabloProject::where('partner_id', $partnerId)
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('school_id');
        if ($classYear) {
            $projectQuery->where('class_year', $classYear);
        }
        $projects = $projectQuery->orderByDesc('class_year')->orderBy('class_name')->get();

        // Diákok lekérése
        $archives = StudentArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $schoolIds)
            ->with('activePhoto')
            ->orderBy('canonical_name')
            ->get();

        // Iskolánként csoportosítás
        $schoolGroups = collect();

        foreach ($schoolIds as $sid) {
            $school = $schools->get($sid);
            if (!$school) {
                continue;
            }

            $sidStudents = $archives->where('school_id', $sid);
            $sidProjects = $projects->where('school_id', $sid);

            $students = $sidStudents->map(function ($s) {
                return [
                    'archiveId' => $s->id,
                    'name' => $s->canonical_name,
                    'className' => $s->class_name,
                    'hasPhoto' => $s->photo_thumb_url !== null,
                    'noPhotoMarked' => $s->notes && str_contains($s->notes, 'Nem találom a képet'),
                    'photoThumbUrl' => $s->photo_thumb_url,
                    'photoUrl' => $s->photo_url,
                ];
            });

            // Rendezés: hiányzó fotó felül, aztán ABC
            $students = $students->sort(function ($a, $b) {
                if ($a['hasPhoto'] !== $b['hasPhoto']) {
                    return $a['hasPhoto'] <=> $b['hasPhoto'];
                }
                return strcmp($a['name'], $b['name']);
            });

            $totalCount = $students->count();
            $missingCount = $students->filter(fn ($s) => !$s['hasPhoto'])->count();

            $classes = $sidProjects->map(fn (TabloProject $p) => [
                'projectId' => $p->id,
                'className' => $p->class_name,
                'classYear' => $p->class_year,
            ])->values()->toArray();

            $schoolGroups->push([
                'schoolId' => $sid,
                'schoolName' => $school->name,
                'classes' => $classes,
                'classCount' => count($classes),
                'studentCount' => $totalCount,
                'missingPhotoCount' => $missingCount,
                'students' => $students->values()->toArray(),
            ]);
        }

        $summary = $this->buildSummary($schoolGroups);

        $displaySchools = $schoolGroups;
        if ($missingOnly) {
            $displaySchools = $schoolGroups->map(function (array $school) {
                $filtered = collect($school['students'])->filter(fn ($s) => !$s['hasPhoto'])->values()->toArray();
                if (empty($filtered)) {
                    return null;
                }
                $school['students'] = $filtered;
                return $school;
            })->filter();
        }

        $displaySchools = $displaySchools->sortByDesc('missingPhotoCount')->values();

        return [
            'schools' => $displaySchools->toArray(),
            'summary' => $summary,
        ];
    }

    private function buildSummary(Collection $schools): array
    {
        $totalStudents = 0;
        $withPhoto = 0;
        $missingPhoto = 0;

        foreach ($schools as $s) {
            $totalStudents += $s['studentCount'];
            $missing = $s['missingPhotoCount'];
            $missingPhoto += $missing;
            $withPhoto += $s['studentCount'] - $missing;
        }

        return [
            'totalSchools' => $schools->count(),
            'totalStudents' => $totalStudents,
            'withPhoto' => $withPhoto,
            'missingPhoto' => $missingPhoto,
        ];
    }
}
