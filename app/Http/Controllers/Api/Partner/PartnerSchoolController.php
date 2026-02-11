<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\StoreSchoolRequest;
use App\Http\Requests\Api\Partner\UpdateSchoolRequest;
use App\Models\SchoolChangeLog;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Models\TeacherArchive;
use App\Services\Search\SearchService;
use App\Helpers\QueryHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Partner School Controller - School management for partners.
 *
 * Handles: schools(), allSchools(), show(), storeSchool(), updateSchool(), deleteSchool(), getChangelog()
 */
class PartnerSchoolController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Get schools list with project counts for partner.
     * Uses partner_schools pivot table for partner-school linkage.
     */
    public function schools(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = TabloPartner::find($partnerId);

        $perPage = min((int) $request->input('per_page', 18), 50);
        $search = $request->input('search');

        // Schools linked to this partner via pivot table
        $query = TabloSchool::select('tablo_schools.*')
            ->whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->withCount([
                'projects as projects_count' => fn ($q) => $q->where('tablo_projects.partner_id', $partnerId),
                'projects as active_projects_count' => fn ($q) => $q->where('tablo_projects.partner_id', $partnerId)
                    ->whereNotIn('tablo_projects.status', ['done', 'in_print']),
            ]);

        // Search using centralized SearchService
        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'city'],
            ]);
        }

        $schools = $query->orderBy('name')->paginate($perPage);

        // linked_group adatok lekérése a pivot-ből
        $pivotData = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereNotNull('linked_group')
            ->get()
            ->keyBy('school_id');

        // Csoportonkénti iskola nevek (linked_group → iskolák)
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

        // Get school limits for this partner (csapattagoknak is működik)
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

        return response()->json($response);
    }

    /**
     * Get schools belonging to the partner for autocomplete.
     */
    public function allSchools(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');

        // Only return schools linked to this partner
        $query = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('city', 'ILIKE', QueryHelper::safeLikePattern($search));
            });
        }

        // Limit to 50 results for autocomplete
        $schools = $query->orderBy('name')->limit(50)->get();

        return response()->json(
            $schools->map(fn ($school) => [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
            ])
        );
    }

    /**
     * Get school detail with stats + recent projects/teachers.
     */
    public function show(int $schoolId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

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

        return response()->json([
            'data' => [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
                'projectsCount' => $projectsCount,
                'activeProjectsCount' => $activeProjectsCount,
                'teachersCount' => $teachersCount,
                'recentProjects' => $recentProjects,
                'recentTeachers' => $recentTeachers,
                'createdAt' => $school->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get paginated changelog for a school.
     */
    public function getChangelog(int $schoolId, Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Verify school belongs to partner
        TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        $perPage = min((int) $request->input('per_page', 50), 50);

        $entries = SchoolChangeLog::where('school_id', $schoolId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $entries->getCollection()->transform(fn ($entry) => [
            'id' => $entry->id,
            'changeType' => $entry->change_type,
            'oldValue' => $entry->old_value,
            'newValue' => $entry->new_value,
            'metadata' => $entry->metadata,
            'userName' => $entry->user?->name,
            'createdAt' => $entry->created_at?->toIso8601String(),
        ]);

        return response()->json($entries);
    }

    /**
     * Create a new school.
     * Links the school to this partner via partner_schools pivot table.
     */
    public function storeSchool(StoreSchoolRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = TabloPartner::find($partnerId);

        // Check school limit (csapattagoknak is működik)
        $partner = auth()->user()->getEffectivePartner();
        if ($partner) {
            $maxSchools = $partner->getMaxSchools();
            if ($maxSchools !== null) {
                $currentCount = $tabloPartner?->schools()->count() ?? 0;
                if ($currentCount >= $maxSchools) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Elérted a csomagodban elérhető maximum iskolaszámot. Válts magasabb csomagra a korlátozás feloldásához!',
                        'upgrade_required' => true,
                    ], 403);
                }
            }
        }

        // Check if school already exists
        $school = TabloSchool::where('name', $request->validated('name'))->first();

        if (! $school) {
            $school = TabloSchool::create([
                'name' => $request->validated('name'),
                'city' => $request->validated('city'),
            ]);
        }

        // Link school to partner via pivot table (if not already linked)
        if ($tabloPartner && ! $tabloPartner->schools()->where('school_id', $school->id)->exists()) {
            $tabloPartner->schools()->attach($school->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen létrehozva',
            'data' => [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
            ],
        ], 201);
    }

    /**
     * Update school (name, city) with changelog logging.
     */
    public function updateSchool(UpdateSchoolRequest $request, int $schoolId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Verify school belongs to partner (via pivot table)
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        $userId = auth()->id();
        $newName = $request->validated('name', $school->name);
        $newCity = $request->validated('city');

        // Log changes before update
        if ($newName !== $school->name) {
            SchoolChangeLog::create([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'change_type' => 'name_changed',
                'old_value' => $school->name,
                'new_value' => $newName,
            ]);
        }

        if ($newCity !== $school->city) {
            SchoolChangeLog::create([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'change_type' => 'city_changed',
                'old_value' => $school->city,
                'new_value' => $newCity,
            ]);
        }

        $school->update([
            'name' => $newName,
            'city' => $newCity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen frissítve',
            'data' => [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
            ],
        ]);
    }

    /**
     * Delete school (unlink from partner, only if no projects).
     */
    public function deleteSchool(int $schoolId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = TabloPartner::find($partnerId);

        // Verify school belongs to partner (via pivot table)
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        // Check if school has any projects (from this partner)
        $projectCount = TabloProject::where('school_id', $schoolId)
            ->where('partner_id', $partnerId)
            ->count();

        if ($projectCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Az iskola nem törölhető, mert {$projectCount} projekt tartozik hozzá.",
            ], 422);
        }

        // Unlink school from partner (remove from pivot table)
        $tabloPartner?->schools()->detach($schoolId);

        // Note: We don't delete the school itself, just unlink it from this partner
        // The school may still be used by other partners

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen törölve',
        ]);
    }
}
