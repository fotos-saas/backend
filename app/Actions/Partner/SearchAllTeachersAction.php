<?php

namespace App\Actions\Partner;

use App\Models\TeacherArchive;
use App\Services\Search\SearchService;
use Illuminate\Support\Collection;

class SearchAllTeachersAction
{
    public function execute(int $partnerId, ?string $search, ?int $schoolId): Collection
    {
        $query = TeacherArchive::forPartner($partnerId)->active()->with('activePhoto');

        if ($schoolId) {
            $query->forSchool($schoolId);
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['canonical_name'],
                'relations' => ['aliases' => ['alias_name']],
            ]);
        }

        $teachers = $query->orderBy('canonical_name')->limit(50)->get();

        return $teachers->map(fn (TeacherArchive $t) => [
            'id' => $t->id,
            'canonicalName' => $t->canonical_name,
            'titlePrefix' => $t->title_prefix,
            'fullDisplayName' => $t->full_display_name,
            'schoolId' => $t->school_id,
            'photoThumbUrl' => $t->photo_thumb_url,
        ]);
    }
}
