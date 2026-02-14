<?php

namespace App\Actions\Partner;

use App\Helpers\QueryHelper;
use App\Models\TeacherArchive;
use App\Services\Search\SearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListTeacherArchiveAction
{
    public function execute(int $partnerId, ?string $search, ?int $schoolId, ?string $classYear, int $perPage): LengthAwarePaginator
    {
        $query = TeacherArchive::forPartner($partnerId)
            ->with('school', 'activePhoto')
            ->withCount('aliases', 'photos');

        if ($schoolId) {
            $query->forSchool($schoolId);
        }

        // Evfolyam szuro: tablo_persons + tablo_projects.class_year alapjan
        if ($classYear) {
            $query->whereIn('id', function ($sub) use ($partnerId, $classYear) {
                $sub->select('ta.id')
                    ->from('teacher_archive as ta')
                    ->join('tablo_persons as tp', function ($join) {
                        $join->on('tp.name', '=', 'ta.canonical_name')
                            ->where('tp.type', 'teacher');
                    })
                    ->join('tablo_projects as tpr', function ($join) use ($partnerId) {
                        $join->on('tpr.id', '=', 'tp.tablo_project_id')
                            ->where('tpr.partner_id', $partnerId);
                    })
                    ->where('tpr.class_year', 'ILIKE', QueryHelper::safeLikePattern($classYear));
            });
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name', 'title_prefix', 'position'],
                'relations' => [
                    'aliases' => ['alias_name'],
                    'school' => ['name'],
                ],
            ]);
        }

        $teachers = $query->orderBy('canonical_name')->paginate($perPage);

        $teachers->getCollection()->transform(fn (TeacherArchive $t) => [
            'id' => $t->id,
            'canonicalName' => $t->canonical_name,
            'titlePrefix' => $t->title_prefix,
            'position' => $t->position,
            'fullDisplayName' => $t->full_display_name,
            'schoolId' => $t->school_id,
            'schoolName' => $t->school?->name,
            'isActive' => $t->is_active,
            'photoThumbUrl' => $t->photo_thumb_url,
            'photoMiniThumbUrl' => $t->photo_mini_thumb_url,
            'photoUrl' => $t->photo_url,
            'aliasesCount' => $t->aliases_count ?? 0,
            'photosCount' => $t->photos_count ?? 0,
            'linkedGroup' => $t->linked_group,
        ]);

        return $teachers;
    }
}
