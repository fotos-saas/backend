<?php

namespace App\Actions\Teacher;

use App\Models\TabloProject;
use App\Models\TeacherAlias;
use App\Models\TeacherArchive;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GetTeachersByProjectAction
{
    /**
     * Projektenként csoportosított tanárok lekérdezése.
     *
     * A tablo_persons (type=teacher) és teacher_archive közötti
     * kapcsolatot canonical_name + school_id egyezés adja.
     */
    public function execute(int $partnerId, ?string $classYear = null, ?int $schoolId = null, bool $missingOnly = false): array
    {
        $query = TabloProject::where('partner_id', $partnerId)
            ->with(['school', 'persons' => fn ($q) => $q->where('type', 'teacher')->with('photo')]);

        if ($classYear) {
            $query->where('class_year', $classYear);
        }
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $query->orderByDesc('class_year')->orderBy('class_name');

        $projects = $query->get();

        // Releváns school_id-k összegyűjtése
        $schoolIds = $projects->pluck('school_id')->filter()->unique()->values()->toArray();

        if (empty($schoolIds)) {
            return $this->buildResponse(collect(), $missingOnly);
        }

        // Batch load: partner összes archive rekordja a releváns iskolákra
        $archives = TeacherArchive::forPartner($partnerId)
            ->whereIn('school_id', $schoolIds)
            ->with('activePhoto')
            ->get();

        // Aliasok batch load
        $archiveIds = $archives->pluck('id')->toArray();
        $aliases = $archiveIds
            ? TeacherAlias::whereIn('teacher_id', $archiveIds)->get()
            : collect();

        // Lookup map építés: [school_id][lowercase_name] => archive
        $archiveMap = [];
        foreach ($archives as $archive) {
            $key = $archive->school_id . '|' . Str::lower(trim($archive->canonical_name));
            $archiveMap[$key] = $archive;
        }

        // Alias lookup map: [school_id][lowercase_alias] => archive
        $aliasMap = [];
        foreach ($aliases as $alias) {
            $archive = $archives->firstWhere('id', $alias->teacher_id);
            if ($archive) {
                $key = $archive->school_id . '|' . Str::lower(trim($alias->alias_name));
                $aliasMap[$key] = $archive;
            }
        }

        // Projektek feldolgozása
        $result = $projects->map(function (TabloProject $project) use ($archiveMap, $aliasMap, $missingOnly) {
            $teachers = $project->persons
                ->filter(fn ($p) => $p->type === 'teacher')
                ->map(function ($person) use ($project, $archiveMap, $aliasMap) {
                    $archive = $this->findArchive($person->name, $project->school_id, $archiveMap, $aliasMap);

                    $hasPhoto = $archive?->photo_thumb_url !== null;

                    return [
                        'personId' => $person->id,
                        'personName' => $person->name,
                        'archiveId' => $archive?->id,
                        'hasPhoto' => $hasPhoto,
                        'photoThumbUrl' => $archive?->photo_thumb_url,
                        'photoUrl' => $archive?->photo_url,
                    ];
                })
                ->values();

            if ($missingOnly) {
                $teachers = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->values();
            }

            $allTeachers = $project->persons->filter(fn ($p) => $p->type === 'teacher');
            $missingCount = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->count();

            // missing_only szűrőnél: ha nincs hiányzó, ugorjuk a projektet
            if ($missingOnly && $teachers->isEmpty()) {
                return null;
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'schoolName' => $project->school?->name,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'teacherCount' => $allTeachers->count(),
                'missingPhotoCount' => $missingCount,
                'teachers' => $teachers->toArray(),
            ];
        })
            ->filter()
            ->values();

        return $this->buildResponse($result, $missingOnly);
    }

    /**
     * Archive keresés: direkt név VAGY alias alapján.
     */
    private function findArchive(string $personName, ?int $schoolId, array $archiveMap, array $aliasMap): ?TeacherArchive
    {
        if (! $schoolId) {
            return null;
        }

        $normalizedName = Str::lower(trim($personName));
        $key = $schoolId . '|' . $normalizedName;

        // 1. Direkt név egyezés
        if (isset($archiveMap[$key])) {
            return $archiveMap[$key];
        }

        // 2. Alias egyezés
        if (isset($aliasMap[$key])) {
            return $aliasMap[$key];
        }

        return null;
    }

    private function buildResponse(Collection $projects, bool $missingOnly): array
    {
        $totalTeachers = 0;
        $withPhoto = 0;
        $missingPhoto = 0;

        foreach ($projects as $p) {
            $totalTeachers += $p['teacherCount'];
            $missing = $p['missingPhotoCount'];
            $missingPhoto += $missing;
            $withPhoto += $p['teacherCount'] - $missing;
        }

        return [
            'projects' => $projects->toArray(),
            'summary' => [
                'totalProjects' => $projects->count(),
                'totalTeachers' => $totalTeachers,
                'withPhoto' => $withPhoto,
                'missingPhoto' => $missingPhoto,
            ],
        ];
    }
}
