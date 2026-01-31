<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrRegistrationCode;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Marketer Controller for frontend-tablo marketer dashboard.
 *
 * Provides access to projects and schools for marketinges/ügyintéző users.
 * Projects are filtered by the user's assigned partner (tablo_partner_id).
 */
class MarketerController extends Controller
{
    /**
     * Get the authenticated user's partner ID or fail with 403.
     */
    private function getPartnerIdOrFail(): int
    {
        $partnerId = auth()->user()->tablo_partner_id;

        if (!$partnerId) {
            abort(403, 'Nincs partnerhez rendelve');
        }

        return $partnerId;
    }

    /**
     * Get a project that belongs to the user's partner.
     */
    private function getProjectForPartner(int $projectId): TabloProject
    {
        return TabloProject::where('id', $projectId)
            ->where('partner_id', $this->getPartnerIdOrFail())
            ->firstOrFail();
    }

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
     * List projects with pagination and search.
     */
    public function projects(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $status = $request->input('status');

        // Validate sort fields
        $allowedSortFields = ['created_at', 'photo_date', 'class_year'];
        if (! in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $query = TabloProject::with(['school', 'contacts', 'tabloStatus', 'qrCodes' => function ($q) {
            $q->active();
        }])
            ->where('partner_id', $partnerId);

        // Search by school name or class name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('class_name', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('school', function ($sq) use ($search) {
                        $sq->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('city', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        $projects = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        // Transform data
        $projects->getCollection()->transform(function ($project) {
            $primaryContact = $project->contacts->firstWhere('is_primary', true)
                ?? $project->contacts->first();

            $activeQrCode = $project->qrCodes->first();

            return [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'tabloStatus' => $project->tabloStatus?->toApiResponse(),
                'photoDate' => $project->photo_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
                'contact' => $primaryContact ? [
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ] : null,
                'hasActiveQrCode' => $activeQrCode !== null,
                'qrCodeId' => $activeQrCode?->id,
                'createdAt' => $project->created_at->toIso8601String(),
            ];
        });

        return response()->json($projects);
    }

    /**
     * Get project details.
     */
    public function projectDetails(int $projectId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $project = TabloProject::with([
            'school',
            'partner',
            'contacts',
            'tabloStatus',
            'qrCodes' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
        ])
            ->where('partner_id', $partnerId)
            ->findOrFail($projectId);

        $primaryContact = $project->contacts->firstWhere('is_primary', true)
            ?? $project->contacts->first();

        $activeQrCode = $project->qrCodes->where('is_active', true)->first();

        return response()->json([
            'id' => $project->id,
            'name' => $project->display_name,
            'school' => $project->school ? [
                'id' => $project->school->id,
                'name' => $project->school->name,
                'city' => $project->school->city,
            ] : null,
            'partner' => $project->partner ? [
                'id' => $project->partner->id,
                'name' => $project->partner->name,
            ] : null,
            'className' => $project->class_name,
            'classYear' => $project->class_year,
            'status' => $project->status?->value,
            'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
            'tabloStatus' => $project->tabloStatus?->toApiResponse(),
            'photoDate' => $project->photo_date?->format('Y-m-d'),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'expectedClassSize' => $project->expected_class_size,
            'contact' => $primaryContact ? [
                'id' => $primaryContact->id,
                'name' => $primaryContact->name,
                'email' => $primaryContact->email,
                'phone' => $primaryContact->phone,
            ] : null,
            'contacts' => $project->contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'isPrimary' => $c->is_primary,
            ]),
            'qrCode' => $activeQrCode ? [
                'id' => $activeQrCode->id,
                'code' => $activeQrCode->code,
                'usageCount' => $activeQrCode->usage_count,
                'maxUsages' => $activeQrCode->max_usages,
                'expiresAt' => $activeQrCode->expires_at?->toIso8601String(),
                'isValid' => $activeQrCode->isValid(),
                'registrationUrl' => $activeQrCode->getRegistrationUrl(),
            ] : null,
            'qrCodesHistory' => $project->qrCodes->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'isActive' => $qr->is_active,
                'usageCount' => $qr->usage_count,
                'createdAt' => $qr->created_at->toIso8601String(),
            ]),
            'createdAt' => $project->created_at->toIso8601String(),
            'updatedAt' => $project->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Get QR code for a project.
     */
    public function getQrCode(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $qrCode = $project->qrCodes()->active()->first();

        if (! $qrCode) {
            return response()->json([
                'hasQrCode' => false,
                'message' => 'Nincs aktív QR kód ehhez a projekthez',
            ]);
        }

        return response()->json([
            'hasQrCode' => true,
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'usageCount' => $qrCode->usage_count,
                'maxUsages' => $qrCode->max_usages,
                'expiresAt' => $qrCode->expires_at?->toIso8601String(),
                'isValid' => $qrCode->isValid(),
                'registrationUrl' => $qrCode->getRegistrationUrl(),
            ],
        ]);
    }

    /**
     * Generate new QR code for a project.
     */
    public function generateQrCode(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Deactivate existing QR codes
        $project->qrCodes()->update(['is_active' => false]);

        // Create new QR code
        $expiresAt = $request->input('expires_at')
            ? \Carbon\Carbon::parse($request->input('expires_at'))
            : now()->addMonths(3);

        $qrCode = QrRegistrationCode::create([
            'tablo_project_id' => $project->id,
            'code' => QrRegistrationCode::generateCode(),
            'is_active' => true,
            'expires_at' => $expiresAt,
            'usage_count' => 0,
            'max_usages' => $request->input('max_usages'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Új QR kód sikeresen létrehozva',
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'usageCount' => $qrCode->usage_count,
                'maxUsages' => $qrCode->max_usages,
                'expiresAt' => $qrCode->expires_at?->toIso8601String(),
                'isValid' => $qrCode->isValid(),
                'registrationUrl' => $qrCode->getRegistrationUrl(),
            ],
        ], 201);
    }

    /**
     * Deactivate (invalidate) a QR code.
     */
    public function deactivateQrCode(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Deactivate all active QR codes for this project
        $updated = $project->qrCodes()->where('is_active', true)->update(['is_active' => false]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs aktív QR kód ehhez a projekthez',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR kód sikeresen inaktiválva',
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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('city', 'ILIKE', "%{$search}%");
            });
        }

        if ($city) {
            $query->where('city', 'ILIKE', "%{$city}%");
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

    // ============================================
    // CONTACT MANAGEMENT
    // ============================================

    /**
     * Add contact to project.
     */
    public function addContact(Request $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése
        if ($request->boolean('isPrimary')) {
            $project->contacts()->update(['is_primary' => false]);
        }

        $contact = $project->contacts()->create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'is_primary' => $request->boolean('isPrimary', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $contact->is_primary,
            ],
        ], 201);
    }

    /**
     * Update contact.
     */
    public function updateContact(Request $request, int $projectId, int $contactId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $contact = $project->contacts()->findOrFail($contactId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése
        if ($request->boolean('isPrimary')) {
            $project->contacts()->where('id', '!=', $contactId)->update(['is_primary' => false]);
        }

        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'is_primary' => $request->has('isPrimary') ? $request->boolean('isPrimary') : $contact->is_primary,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $contact->is_primary,
            ],
        ]);
    }

    /**
     * Delete contact.
     */
    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $contact = $project->contacts()->findOrFail($contactId);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }

    // ============================================
    // PROJECT CREATION
    // ============================================

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
     * Create a new project for the user's partner.
     */
    public function storeProject(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
        ], [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create the project
        $project = TabloProject::create([
            'partner_id' => $partnerId,
            'school_id' => $request->input('school_id'),
            'class_name' => $request->input('class_name'),
            'class_year' => $request->input('class_year'),
            'status' => \App\Enums\TabloProjectStatus::NotStarted,
        ]);

        // Load relations for response
        $project->load(['school']);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen létrehozva',
            'data' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'tabloStatus' => null,
                'photoDate' => null,
                'deadline' => null,
                'contact' => null,
                'hasActiveQrCode' => false,
                'qrCodeId' => null,
                'createdAt' => $project->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
