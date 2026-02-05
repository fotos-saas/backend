<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Models\QrRegistrationCode;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketer Controller — Dashboard statistics, schools, and cities.
 */
class MarketerController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $totalProjects = TabloProject::where('partner_id', $partnerId)->count();

        $activeQrCodes = QrRegistrationCode::active()
            ->whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->count();

        // Schools that have projects for this partner
        $totalSchools = TabloSchool::whereHas('projects', fn ($q) => $q->where('partner_id', $partnerId))
            ->count();

        // Projects by status
        $projectsByStatus = TabloProject::where('partner_id', $partnerId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'totalProjects' => $totalProjects,
            'activeQrCodes' => $activeQrCodes,
            'totalSchools' => $totalSchools,
            'projectsByStatus' => $projectsByStatus,
        ]);
    }

    /**
     * List schools with pagination and search.
     * Only returns schools that have projects for the user's partner.
     */
    public function schools(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $city = $request->input('city');

        $query = TabloSchool::whereHas('projects', fn ($q) => $q->where('partner_id', $partnerId))
            ->withCount(['projects' => fn ($q) => $q->where('partner_id', $partnerId)]);

        if ($search) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhere('city', 'ILIKE', $pattern);
            });
        }

        if ($city) {
            $query->where('city', 'ILIKE', QueryHelper::safeLikePattern($city));
        }

        $schools = $query->orderBy('name')->paginate($perPage);

        $schools->getCollection()->transform(function ($school) {
            return [
                'id' => $school->id,
                'name' => $school->name,
                'city' => $school->city,
                'projectsCount' => $school->projects_count,
            ];
        });

        return response()->json($schools);
    }

    /**
     * Get list of unique cities for filtering.
     * Only returns cities from schools that have projects for the user's partner.
     */
    public function cities(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $cities = TabloSchool::whereHas('projects', fn ($q) => $q->where('partner_id', $partnerId))
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return response()->json($cities);
    }

    /**
     * Get all schools for project creation dropdown.
     * Returns all schools in the system (not filtered by partner).
     */
    public function allSchools(Request $request): JsonResponse
    {
        // Verify that user has a partner (required for creating projects)
        $this->getPartnerIdOrFail();

        $search = $request->input('search');

        $query = TabloSchool::query();

        if ($search) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhere('city', 'ILIKE', $pattern);
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
}
