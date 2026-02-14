<?php

namespace App\Actions\Student;

use App\Helpers\QueryHelper;
use App\Models\StudentArchive;
use App\Services\Search\SearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStudentArchiveAction
{
    public function execute(int $partnerId, ?string $search, ?int $schoolId, ?string $className, int $perPage): LengthAwarePaginator
    {
        $query = StudentArchive::forPartner($partnerId)
            ->with('school', 'activePhoto')
            ->withCount('aliases', 'photos');

        if ($schoolId) {
            $query->forSchool($schoolId);
        }

        if ($className) {
            $query->where('class_name', 'ILIKE', QueryHelper::safeLikePattern($className));
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name', 'class_name'],
                'relations' => [
                    'aliases' => ['alias_name'],
                    'school' => ['name'],
                ],
            ]);
        }

        $students = $query->orderBy('canonical_name')->paginate($perPage);

        $students->getCollection()->transform(fn (StudentArchive $s) => [
            'id' => $s->id,
            'canonicalName' => $s->canonical_name,
            'className' => $s->class_name,
            'schoolId' => $s->school_id,
            'schoolName' => $s->school?->name,
            'isActive' => $s->is_active,
            'photoThumbUrl' => $s->photo_thumb_url,
            'photoMiniThumbUrl' => $s->photo_mini_thumb_url,
            'photoUrl' => $s->photo_url,
            'aliasesCount' => $s->aliases_count ?? 0,
            'photosCount' => $s->photos_count ?? 0,
        ]);

        return $students;
    }
}
