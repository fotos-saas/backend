<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrRegistrationCode;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin Controller for frontend-tablo super admin dashboard.
 *
 * Provides system-wide statistics and management for super admin users.
 */
class SuperAdminController extends Controller
{
    /**
     * Dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $totalPartners = TabloPartner::count();
        $totalProjects = TabloProject::count();

        $activeQrCodes = QrRegistrationCode::active()->count();

        // Projects by status
        $projectsByStatus = TabloProject::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'totalPartners' => $totalPartners,
            'totalProjects' => $totalProjects,
            'activeQrCodes' => $activeQrCodes,
            'projectsByStatus' => $projectsByStatus,
        ]);
    }

    /**
     * List partners with pagination and search.
     */
    public function partners(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = TabloPartner::query()
            ->withCount('projects');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $allowedSortFields = ['name', 'email', 'created_at', 'projects_count'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $partners = $query->paginate($perPage);

        return response()->json([
            'data' => $partners->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'schoolName' => $partner->email, // email as secondary info
                'hasActiveQrCode' => false, // partner level doesn't have QR
            ]),
            'current_page' => $partners->currentPage(),
            'last_page' => $partners->lastPage(),
            'per_page' => $partners->perPage(),
            'total' => $partners->total(),
            'from' => $partners->firstItem(),
            'to' => $partners->lastItem(),
        ]);
    }
}
