<?php

namespace App\Actions\Partner;

use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Models\TeacherArchive;

class GetSchoolDetailAction
{
    public function execute(int $partnerId, int $schoolId): array
    {
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        $projectsCount = TabloProject::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->count();

        $activeProjectsCount = TabloProject::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->whereNotIn('status', ['done', 'in_print'])
            ->count();

        $teachersCount = TeacherArchive::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->count();

        $recentProjects = TabloProject::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'className' => $p->class_name,
                'status' => $p->status,
                'createdAt' => $p->created_at?->toIso8601String(),
            ]);

        $recentTeachers = TeacherArchive::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'canonicalName' => $t->canonical_name,
                'position' => $t->position,
            ]);

        return [
            'id' => $school->id,
            'name' => $school->name,
            'city' => $school->city,
            'projectsCount' => $projectsCount,
            'activeProjectsCount' => $activeProjectsCount,
            'teachersCount' => $teachersCount,
            'recentProjects' => $recentProjects,
            'recentTeachers' => $recentTeachers,
            'createdAt' => $school->created_at?->toIso8601String(),
        ];
    }
}
