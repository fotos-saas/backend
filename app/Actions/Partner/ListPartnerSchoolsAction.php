<?php

namespace App\Actions\Partner;

use App\Models\TabloPartner;
use App\Models\TabloSchool;
use App\Services\Search\SearchService;
use Illuminate\Support\Facades\DB;

class ListPartnerSchoolsAction
{
    public function execute(int $partnerId, ?string $search, int $perPage, ?int $graduationYear = null): array
    {
        $tabloPartner = TabloPartner::find($partnerId);

        $query = TabloSchool::select('tablo_schools.*')
            ->whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->withCount([
                'projects as projects_count' => fn ($q) => $q->where('tablo_projects.partner_id', $partnerId),
                'projects as active_projects_count' => fn ($q) => $q->where('tablo_projects.partner_id', $partnerId)
                    ->whereNotIn('tablo_projects.status', ['done', 'in_print']),
            ]);

        // Évfolyam szűrő: csak azok az iskolák, amiknek van ilyen évfolyamú projektje
        if ($graduationYear) {
            $query->whereHas('projects', fn ($q) => $q
                ->where('tablo_projects.partner_id', $partnerId)
                ->where('tablo_projects.graduation_year', $graduationYear)
            );
        }

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'city'],
                'id_column' => 'tablo_schools.id',
            ]);
        }

        $schools = $query->orderBy('name')->paginate($perPage);

        // linked_group adatok lekerdese a pivot-bol
        $pivotData = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereNotNull('linked_group')
            ->get()
            ->keyBy('school_id');

        // Csoportonkenti iskola nevek (linked_group -> iskolak)
        $groupSchools = DB::table('partner_schools')
            ->join('tablo_schools', 'tablo_schools.id', '=', 'partner_schools.school_id')
            ->where('partner_schools.partner_id', $partnerId)
            ->whereNotNull('partner_schools.linked_group')
            ->select('partner_schools.linked_group', 'tablo_schools.id', 'tablo_schools.name', 'tablo_schools.city')
            ->get()
            ->groupBy('linked_group');

        $schools->getCollection()->transform(function ($school) use ($pivotData, $groupSchools) {
            $pivot = $pivotData->get($school->id);
            $linkedGroup = $pivot?->linked_group;
            $linkedSchools = [];

            if ($linkedGroup && $groupSchools->has($linkedGroup)) {
                $linkedSchools = $groupSchools->get($linkedGroup)
                    ->where('id', '!=', $school->id)
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'city' => $s->city])
                    ->values()
                    ->toArray();
            }

            return [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
                'projectsCount' => $school->projects_count ?? 0,
                'activeProjectsCount' => $school->active_projects_count ?? 0,
                'hasActiveProjects' => ($school->active_projects_count ?? 0) > 0,
                'linkedGroup' => $linkedGroup,
                'linkedSchools' => $linkedSchools,
            ];
        });

        // Get school limits for this partner
        $partner = auth()->user()->getEffectivePartner();
        $maxSchools = $partner?->getMaxSchools();
        $currentCount = $tabloPartner?->schools()->count() ?? 0;

        $response = $schools->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxSchools,
            'can_create' => $maxSchools === null || $currentCount < $maxSchools,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return $response;
    }
}
