<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner School Controller - School management for partners.
 *
 * Handles: schools(), allSchools(), storeSchool(), updateSchool(), deleteSchool()
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
            ->whereHas('partners', fn ($q) => $q->where('partner_id', $partnerId))
            ->withCount([
                'projects as projects_count' => fn ($q) => $q->where('partner_id', $partnerId),
                'projects as active_projects_count' => fn ($q) => $q->where('partner_id', $partnerId)
                    ->whereNotIn('status', ['done', 'in_print']),
            ]);

        // Search using centralized SearchService
        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'city'],
            ]);
        }

        $schools = $query->orderBy('name')->paginate($perPage);

        $schools->getCollection()->transform(fn ($school) => [
            'id' => $school->id,
            'name' => $school->name,
            'city' => $school->city,
            'projectsCount' => $school->projects_count ?? 0,
            'activeProjectsCount' => $school->active_projects_count ?? 0,
            'hasActiveProjects' => ($school->active_projects_count ?? 0) > 0,
        ]);

        // Get school limits for this partner
        $partner = auth()->user()->partner;
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
        $query = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_id', $partnerId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('city', 'ILIKE', "%{$search}%");
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
     * Create a new school.
     * Links the school to this partner via partner_schools pivot table.
     */
    public function storeSchool(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = TabloPartner::find($partnerId);

        // Check school limit
        $partner = auth()->user()->partner;
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Az iskola neve kötelező.',
            'name.max' => 'Az iskola neve maximum 255 karakter lehet.',
            'city.max' => 'A város neve maximum 255 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if school already exists
        $school = TabloSchool::where('name', $request->input('name'))->first();

        if (!$school) {
            $school = TabloSchool::create([
                'name' => $request->input('name'),
                'city' => $request->input('city'),
            ]);
        }

        // Link school to partner via pivot table (if not already linked)
        if ($tabloPartner && !$tabloPartner->schools()->where('school_id', $school->id)->exists()) {
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
     * Update school (name, city).
     */
    public function updateSchool(Request $request, int $schoolId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Verify school belongs to partner (via pivot table)
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_id', $partnerId))
            ->findOrFail($schoolId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'city' => 'nullable|string|max:255',
        ], [
            'name.max' => 'Az iskola neve maximum 255 karakter lehet.',
            'city.max' => 'A város neve maximum 255 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $school->update([
            'name' => $request->input('name', $school->name),
            'city' => $request->input('city'),
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
        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_id', $partnerId))
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
