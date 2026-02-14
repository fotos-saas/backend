<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Partner\GenerateSchoolTeacherPhotosZipAction;
use App\Actions\Partner\GetSchoolDetailAction;
use App\Actions\Partner\ListPartnerSchoolsAction;
use App\Actions\Partner\StoreSchoolAction;
use App\Actions\Partner\UpdateSchoolAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\StoreSchoolRequest;
use App\Http\Requests\Api\Partner\UpdateSchoolRequest;
use App\Models\SchoolChangeLog;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Helpers\QueryHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
    public function schools(Request $request, ListPartnerSchoolsAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $perPage = min((int) $request->input('per_page', 18), 50);

        $graduationYear = $request->filled('graduation_year') ? (int) $request->input('graduation_year') : null;

        $response = $action->execute($partnerId, $request->input('search'), $perPage, $graduationYear);

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
    public function show(int $schoolId, GetSchoolDetailAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        return response()->json([
            'data' => $action->execute($partnerId, $schoolId),
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
    public function storeSchool(StoreSchoolRequest $request, StoreSchoolAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $result = $action->execute(
            $partnerId,
            $request->validated('name'),
            $request->validated('city'),
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'upgrade_required' => $result['upgrade_required'] ?? false,
            ], $result['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen létrehozva',
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Update school (name, city) with changelog logging.
     */
    public function updateSchool(UpdateSchoolRequest $request, int $schoolId, UpdateSchoolAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $data = $action->execute(
            $partnerId,
            $schoolId,
            $request->validated('name'),
            $request->validated('city'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Iskola sikeresen frissítve',
            'data' => $data,
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

    /**
     * Tanári aktív fotók ZIP letöltése iskolához.
     */
    public function downloadTeacherPhotosZip(
        Request $request,
        int $schoolId,
        GenerateSchoolTeacherPhotosZipAction $action,
    ): BinaryFileResponse|JsonResponse {
        $partnerId = $this->getPartnerIdOrFail();

        $school = TabloSchool::whereHas('partners', fn ($q) => $q->where('partner_schools.partner_id', $partnerId))
            ->findOrFail($schoolId);

        $fileNaming = $request->input('file_naming', 'student_name');
        $allProjects = (bool) $request->input('all_projects', false);

        $zipPath = $action->execute($schoolId, $partnerId, $school->name, $fileNaming, $allProjects);

        $filename = "tanarok-{$school->id}-" . now()->format('Y-m-d') . '.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
