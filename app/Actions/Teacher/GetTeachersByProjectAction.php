<?php

namespace App\Actions\Teacher;

use App\Helpers\QueryHelper;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use Illuminate\Support\Collection;

class GetTeachersByProjectAction
{
    /**
     * Projektenként csoportosított tanárok lekérdezése.
     *
     * Logika: projekt school_id → teacher_archive rekordok az adott iskolához.
     * Így minden projekt megjelenik, nem csak azok amelyeknek van tablo_persons rekordja.
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

        // Projektek feldolgozása
        $result = $projects->map(function (TabloProject $project) use ($archivesBySchool, $missingOnly) {
            $schoolTeachers = $archivesBySchool->get($project->school_id, collect());

            $teachers = $schoolTeachers->map(fn (TeacherArchive $t) => [
                'personId' => null,
                'personName' => $t->full_display_name,
                'archiveId' => $t->id,
                'hasPhoto' => $t->photo_thumb_url !== null,
                'photoThumbUrl' => $t->photo_thumb_url,
                'photoUrl' => $t->photo_url,
            ]);

            $totalCount = $teachers->count();
            $missingCount = $teachers->filter(fn ($t) => ! $t['hasPhoto'])->count();

            if ($missingOnly) {
                $teachers = $teachers->filter(fn ($t) => ! $t['hasPhoto']);
            }

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
                'teacherCount' => $totalCount,
                'missingPhotoCount' => $missingCount,
                'teachers' => $teachers->values()->toArray(),
            ];
        })
            ->filter()
            ->values();

        return $this->buildResponse($result);
    }

    private function buildResponse(Collection $projects): array
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
