<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrRegistrationCode;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Partner Controller for frontend-tablo partner dashboard.
 *
 * Provides access to projects and statistics for partner (fotós) users.
 * Projects are filtered by the user's assigned partner (tablo_partner_id).
 */
class PartnerController extends Controller
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
     * Dashboard statistics for partner.
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

        // Upcoming photoshoots (photo_date in the future)
        $upcomingPhotoshoots = TabloProject::where('partner_id', $partnerId)
            ->whereNotNull('photo_date')
            ->where('photo_date', '>=', now()->startOfDay())
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
            'upcomingPhotoshoots' => $upcomingPhotoshoots,
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
        $allowedSortFields = ['created_at', 'photo_date', 'class_year', 'school_name', 'tablo_status', 'missing_count', 'samples_count'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $query = TabloProject::with(['school', 'contacts', 'tabloStatus', 'qrCodes' => function ($q) {
            $q->active();
        }, 'media' => function ($q) {
            // Load both samples and tablo_pending collections
            $q->whereIn('collection_name', ['samples', 'tablo_pending']);
        }])
            ->withCount([
                'guestSessions as guests_count' => fn ($q) => $q->where('is_banned', false),
                'missingPersons as missing_count' => fn ($q) => $q->whereNull('media_id'),
                'missingPersons as missing_students_count' => fn ($q) => $q->whereNull('media_id')->where('type', 'student'),
                'missingPersons as missing_teachers_count' => fn ($q) => $q->whereNull('media_id')->where('type', 'teacher'),
            ])
            ->where('partner_id', $partnerId);

        // Search using centralized SearchService
        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'class_name'],
                'relations' => [
                    'school' => ['name', 'city'],
                    'contacts' => ['name', 'email'],
                    'missingPersons' => ['name'],
                ],
                'prefixes' => [
                    '@' => ['contacts' => ['name', 'email']],
                ],
            ]);
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by is_aware
        if ($request->filled('is_aware')) {
            $query->where('is_aware', $request->input('is_aware') === 'true');
        }

        // Filter by has_draft_photos
        if ($request->filled('has_draft')) {
            $hasDraft = $request->input('has_draft') === 'true';
            if ($hasDraft) {
                $query->whereHas('media', fn ($q) => $q->where('collection_name', 'tablo_pending'));
            } else {
                $query->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'tablo_pending'));
            }
        }

        // Handle special sort fields - use subqueries to avoid interfering with withCount
        if ($sortBy === 'school_name') {
            $query->orderBy(
                TabloSchool::select('name')
                    ->whereColumn('tablo_schools.id', 'tablo_projects.school_id')
                    ->limit(1),
                $sortDir
            );
        } elseif ($sortBy === 'tablo_status') {
            $query->orderBy(
                \App\Models\TabloStatus::select('sort_order')
                    ->whereColumn('tablo_statuses.id', 'tablo_projects.tablo_status_id')
                    ->limit(1),
                $sortDir
            );
        } elseif ($sortBy === 'missing_count') {
            $query->orderBy('missing_count', $sortDir);
        } elseif ($sortBy === 'samples_count') {
            // samples_count requires subquery since it's from media library
            $query->withCount('media as samples_count')
                ->orderBy('samples_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $projects = $query->paginate($perPage);

        // Get project limits for this partner
        $partner = auth()->user()->partner;
        $maxClasses = $partner?->getMaxClasses();
        $currentCount = TabloProject::where('partner_id', $partnerId)->count();

        // Transform data
        $projects->getCollection()->transform(function ($project) {
            // Get primary contact from pivot (is_primary is in pivot table)
            $primaryContact = $project->contacts->first(fn ($c) => $c->pivot->is_primary ?? false)
                ?? $project->contacts->first();

            $activeQrCode = $project->qrCodes->first();

            // Get samples from eager loaded media
            $samples = $project->media->where('collection_name', 'samples');
            $firstSample = $samples->first();

            // Count draft photos from eager loaded media
            $draftPhotoCount = $project->media
                ->where('collection_name', 'tablo_pending')
                ->count();

            return [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'statusColor' => $project->status?->tailwindColor() ?? 'gray',
                'tabloStatus' => $project->tabloStatus?->toApiResponse(),
                'photoDate' => $project->photo_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
                'guestsCount' => $project->guests_count ?? 0,
                'expectedClassSize' => $project->expected_class_size,
                'missingCount' => $project->missing_count ?? 0,
                'missingStudentsCount' => $project->missing_students_count ?? 0,
                'missingTeachersCount' => $project->missing_teachers_count ?? 0,
                'samplesCount' => $samples->count(),
                'sampleThumbUrl' => $firstSample?->getUrl('thumb'),
                'draftPhotoCount' => $draftPhotoCount,
                'contact' => $primaryContact ? [
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ] : null,
                'hasActiveQrCode' => $activeQrCode !== null,
                'isAware' => $project->is_aware,
                'createdAt' => $project->created_at->toIso8601String(),
            ];
        });

        // Add limits to pagination response
        $response = $projects->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxClasses,
            'can_create' => $maxClasses === null || $currentCount < $maxClasses,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return response()->json($response);
    }

    /**
     * Get project details.
     */
    public function projectDetails(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $project->load([
            'school',
            'partner',
            'contacts',
            'tabloStatus',
            'qrCodes' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
        ]);

        $project->loadCount([
            'guestSessions as guests_count' => fn ($q) => $q->where('is_banned', false),
            'missingPersons as missing_count' => fn ($q) => $q->whereNull('media_id'),
        ]);

        // Get primary contact from pivot (is_primary is now in pivot table)
        $primaryContact = $project->contacts->first(fn ($c) => $c->pivot->is_primary)
            ?? $project->contacts->first();

        $activeQrCode = $project->qrCodes->where('is_active', true)->first();
        $samplesCount = $project->getMedia('samples')->count();

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
            'guestsCount' => $project->guests_count ?? 0,
            'missingCount' => $project->missing_count ?? 0,
            'samplesCount' => $samplesCount,
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
                'isPrimary' => $c->pivot->is_primary ?? false,
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
     * Get project samples (images from media library).
     */
    public function projectSamples(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $samples = $project->getMedia('samples')->map(fn ($media) => [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumbnailUrl' => $media->getUrl('thumb'),
            'name' => $media->file_name,
        ]);

        return response()->json([
            'data' => $samples,
        ]);
    }

    /**
     * Get project missing persons.
     */
    public function projectMissingPersons(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $withoutPhoto = $request->boolean('without_photo', false);

        $query = $project->missingPersons()->orderBy('position');

        if ($withoutPhoto) {
            $query->whereNull('media_id');
        }

        $missingPersons = $query->with('photo')->get()->map(function ($person) {
            return [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'hasPhoto' => $person->hasPhoto(),
                'email' => $person->email,
                'photoThumbUrl' => $person->photo_thumb_url,
                'photoUrl' => $person->photo_url,
            ];
        });

        return response()->json([
            'data' => $missingPersons,
        ]);
    }

    // ============================================
    // PROJECT CREATION
    // ============================================

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
     * Get all contacts from partner for autocomplete.
     * Returns unique contacts (by email) from the partner.
     */
    public function allContacts(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');

        // Get all contacts that belong to this partner
        $query = \App\Models\TabloContact::where('partner_id', $partnerId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        // Get unique contacts by email (or name if no email)
        $contacts = $query->orderBy('name')
            ->limit(50)
            ->get()
            ->unique(fn ($contact) => $contact->email ?? $contact->name)
            ->values();

        return response()->json(
            $contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])
        );
    }

    /**
     * Create a new contact (standalone, will be linked to project on project creation).
     */
    public function storeContact(Request $request): JsonResponse
    {
        $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ], [
            'name.required' => 'A név megadása kötelező.',
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Return contact data without creating (will be created with project)
        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó adatok érvényesek',
            'data' => [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ],
        ]);
    }

    /**
     * Create a new school.
     * Links the school to this partner via partner_schools pivot table.
     */
    public function storeSchool(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = \App\Models\TabloPartner::find($partnerId);

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
     * Create a new project for the user's partner.
     */
    public function storeProject(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Check project limit
        $partner = auth()->user()->partner;
        if ($partner) {
            $maxClasses = $partner->getMaxClasses();
            if ($maxClasses !== null) {
                $currentCount = TabloProject::where('partner_id', $partnerId)->count();
                if ($currentCount >= $maxClasses) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Elérted a csomagodban elérhető maximum projektszámot. Válts magasabb csomagra a korlátozás feloldásához!',
                        'upgrade_required' => true,
                    ], 403);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
            'photo_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'expected_class_size' => 'nullable|integer|min:1|max:500',
            // Contact fields
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ], [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
            'photo_date.date' => 'Érvénytelen fotózás dátum.',
            'deadline.date' => 'Érvénytelen határidő dátum.',
            'expected_class_size.integer' => 'A várható létszámnak egész számnak kell lennie.',
            'expected_class_size.min' => 'A várható létszámnak legalább 1-nek kell lennie.',
            'expected_class_size.max' => 'A várható létszám maximum 500 lehet.',
            'contact_email.email' => 'Érvénytelen email cím.',
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
            'photo_date' => $request->input('photo_date'),
            'deadline' => $request->input('deadline'),
            'expected_class_size' => $request->input('expected_class_size'),
            'status' => \App\Enums\TabloProjectStatus::NotStarted,
        ]);

        // Create contact if provided
        $contact = null;
        if ($request->filled('contact_name')) {
            $contact = \App\Models\TabloContact::create([
                'partner_id' => $partnerId,
                'name' => $request->input('contact_name'),
                'email' => $request->input('contact_email'),
                'phone' => $request->input('contact_phone'),
            ]);
            // Link contact to project via pivot with is_primary = true
            $contact->projects()->attach($project->id, ['is_primary' => true]);
        }

        // Load relations for response
        $project->load(['school', 'contacts']);

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
                'photoDate' => $project->photo_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
                'expectedClassSize' => $project->expected_class_size,
                'guestsCount' => 0,
                'missingCount' => 0,
                'samplesCount' => 0,
                'contact' => null,
                'hasActiveQrCode' => false,
                'createdAt' => $project->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update an existing project.
     */
    public function updateProject(Request $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
            'photo_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'expected_class_size' => 'nullable|integer|min:1|max:500',
        ], [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
            'photo_date.date' => 'Érvénytelen fotózás dátum.',
            'deadline.date' => 'Érvénytelen határidő dátum.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update the project
        $project->update([
            'school_id' => $request->input('school_id'),
            'class_name' => $request->input('class_name'),
            'class_year' => $request->input('class_year'),
            'photo_date' => $request->input('photo_date'),
            'deadline' => $request->input('deadline'),
            'expected_class_size' => $request->input('expected_class_size', $project->expected_class_size),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen módosítva',
        ]);
    }

    /**
     * Delete a project.
     */
    public function deleteProject(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen törölve',
        ]);
    }

    // ============================================
    // QR CODE MANAGEMENT
    // ============================================

    /**
     * Get QR code for a project.
     */
    public function getQrCode(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $qrCode = $project->qrCodes()->active()->first();

        if (!$qrCode) {
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

    // ============================================
    // CONTACT MANAGEMENT
    // ============================================

    /**
     * Add contact to project.
     */
    public function addContact(Request $request, int $projectId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
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

        $isPrimary = $request->boolean('isPrimary', false);

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($isPrimary) {
            $project->contacts()->updateExistingPivot(
                $project->contacts()->pluck('tablo_contacts.id')->toArray(),
                ['is_primary' => false]
            );
        }

        // Create contact with partner_id
        $contact = \App\Models\TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Link to project via pivot
        $contact->projects()->attach($projectId, ['is_primary' => $isPrimary]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ], 201);
    }

    /**
     * Update contact.
     */
    public function updateContact(Request $request, int $projectId, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Find contact that is linked to this project and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

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

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($request->boolean('isPrimary')) {
            // Reset all other contacts' is_primary for this project
            $project->contacts()
                ->where('tablo_contacts.id', '!=', $contactId)
                ->each(fn ($c) => $project->contacts()->updateExistingPivot($c->id, ['is_primary' => false]));
        }

        // Update contact basic fields
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Update is_primary in pivot if provided
        if ($request->has('isPrimary')) {
            $project->contacts()->updateExistingPivot($contactId, [
                'is_primary' => $request->boolean('isPrimary'),
            ]);
        }

        // Get updated pivot data
        $isPrimary = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->first()?->pivot?->is_primary ?? false;

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ]);
    }

    /**
     * Delete contact from project (detach from pivot).
     * Note: This only removes the project-contact link, not the contact itself.
     * If you want to delete the contact entirely, use deleteStandaloneContact().
     */
    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Verify contact exists and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

        // Detach from this project (doesn't delete the contact)
        $project->contacts()->detach($contactId);

        // If contact has no more projects, delete it entirely
        if ($contact->projects()->count() === 0) {
            $contact->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve a projektből',
        ]);
    }

    // ============================================
    // ALBUM MANAGEMENT
    // ============================================

    /**
     * Get albums summary (both students and teachers).
     */
    public function getAlbums(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerAlbumService $albumService */
        $albumService = app(\App\Services\PartnerAlbumService::class);

        // Árva képek automatikus migrálása
        $albumService->migrateOrphanPhotos($project);

        return response()->json([
            'albums' => $albumService->getAlbumsSummary($project),
        ]);
    }

    /**
     * Get single album details (photos + missing persons).
     */
    public function getAlbum(int $projectId, string $album): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerAlbumService $albumService */
        $albumService = app(\App\Services\PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $details = $albumService->getAlbumDetails($project, $album);

        return response()->json([
            'album' => $details,
        ]);
    }

    /**
     * Upload photos to a specific album.
     */
    public function uploadToAlbum(int $projectId, string $album, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerAlbumService $albumService */
        $albumService = app(\App\Services\PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480', // max 20MB per file
            'zip' => 'required_without:photos|file|mimes:zip|max:524288', // max 512MB
        ], [
            'photos.required_without' => 'Képek vagy ZIP fájl megadása kötelező.',
            'photos.max' => 'Maximum 50 kép tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 20MB.',
            'zip.mimes' => 'Csak ZIP fájl engedélyezett.',
            'zip.max' => 'Maximum ZIP méret: 512MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        $uploadedMedia = collect();

        // ZIP feltöltés
        if ($request->hasFile('zip')) {
            $uploadedMedia = $photoService->uploadFromZip($project, $request->file('zip'), $album);
        }
        // Egyedi képek
        elseif ($request->hasFile('photos')) {
            $uploadedMedia = $photoService->bulkUpload($project, $request->file('photos'), $album);
        }

        return response()->json([
            'success' => true,
            'uploadedCount' => $uploadedMedia->count(),
            'album' => $album,
            'photos' => $uploadedMedia->map(fn ($media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
            ])->values(),
        ]);
    }

    /**
     * Clear all photos from an album.
     */
    public function clearAlbum(int $projectId, string $album): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerAlbumService $albumService */
        $albumService = app(\App\Services\PartnerAlbumService::class);

        if (! $albumService->isValidAlbum($album)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen album típus. Használható: students, teachers',
            ], 400);
        }

        $deletedCount = $albumService->clearAlbum($project, $album);

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} kép törölve az albumból.",
            'deletedCount' => $deletedCount,
        ]);
    }

    /**
     * Delete pending photos by media IDs.
     */
    public function deletePendingPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen adatok.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mediaIds = array_map('intval', $request->input('media_ids'));

        // Töröljük a tablo_pending collection-ből a megadott média rekordokat
        $deletedCount = $project->getMedia('tablo_pending')
            ->filter(fn ($m) => in_array($m->id, $mediaIds, true))
            ->each(fn ($m) => $m->delete())
            ->count();

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} kép törölve.",
            'deleted_count' => $deletedCount,
        ]);
    }

    // ============================================
    // PHOTO UPLOAD & MATCHING
    // ============================================

    /**
     * Bulk upload photos (images or ZIP).
     * @deprecated Use uploadToAlbum() instead
     */
    public function bulkUploadPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480', // max 20MB per file
            'zip' => 'required_without:photos|file|mimes:zip|max:524288', // max 512MB
            'album' => 'nullable|string|in:students,teachers',
        ], [
            'photos.required_without' => 'Képek vagy ZIP fájl megadása kötelező.',
            'photos.max' => 'Maximum 50 kép tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 20MB.',
            'zip.mimes' => 'Csak ZIP fájl engedélyezett.',
            'zip.max' => 'Maximum ZIP méret: 512MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        $uploadedMedia = collect();

        // Album - alapértelmezetten 'students'
        $album = $request->input('album', 'students');

        // ZIP feltöltés
        if ($request->hasFile('zip')) {
            $uploadedMedia = $photoService->uploadFromZip($project, $request->file('zip'), $album);
        }
        // Egyedi képek
        elseif ($request->hasFile('photos')) {
            $uploadedMedia = $photoService->bulkUpload($project, $request->file('photos'), $album);
        }

        return response()->json([
            'success' => true,
            'uploadedCount' => $uploadedMedia->count(),
            'album' => $album,
            'photos' => $uploadedMedia->map(fn ($media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
            ])->values(),
        ]);
    }

    /**
     * Get pending photos (uploaded but not yet matched).
     */
    public function getPendingPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        return response()->json([
            'photos' => $photoService->getPendingPhotos($project),
        ]);
    }

    /**
     * Match photos with missing persons using AI.
     */
    public function matchPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        // Pending képek lekérése
        $photos = $project->getMedia('tablo_pending');

        // Ha van szűrés media ID-kra
        if ($request->filled('photoIds')) {
            $photoIds = array_map('intval', $request->input('photoIds', []));
            // Collection filter - a whereIn nem működik jól Spatie Media objektumokon
            $photos = $photos->filter(fn ($m) => in_array($m->id, $photoIds, true));
        }

        if ($photos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincsenek feltöltött képek a párosításhoz.',
            ], 400);
        }

        // Még párosítatlan személyek
        $persons = $project->missingPersons()
            ->whereNull('media_id')
            ->orderBy('position')
            ->get();

        if ($persons->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs párosítatlan személy a listában.',
            ], 400);
        }

        // Fájlok összeállítása a matcher-hez
        $files = $photos->map(fn ($m) => [
            'filename' => $m->file_name,
            'title' => $m->getCustomProperty('iptc_title'),
            'mediaId' => $m->id,
        ])->values()->toArray();

        $names = $persons->pluck('name')->toArray();

        // AI párosítás
        /** @var \App\Services\NameMatcherService $matcherService */
        $matcherService = app(\App\Services\NameMatcherService::class);

        try {
            $result = $matcherService->match($names, $files);

            return response()->json([
                'success' => true,
                'matches' => $result->matches,
                'uncertain' => $result->uncertain,
                'unmatchedNames' => $result->unmatchedNames,
                'unmatchedFiles' => $result->unmatchedFiles,
                'summary' => $result->getSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI párosítás sikertelen: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign photos to missing persons (finalize matching).
     */
    public function assignPhotos(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.personId' => 'required|integer',
            'assignments.*.mediaId' => 'required|integer',
        ], [
            'assignments.required' => 'Legalább egy párosítás megadása kötelező.',
            'assignments.*.personId.required' => 'Személy ID megadása kötelező.',
            'assignments.*.mediaId.required' => 'Média ID megadása kötelező.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        $assignedCount = $photoService->assignPhotos(
            $project,
            $request->input('assignments')
        );

        return response()->json([
            'success' => true,
            'assignedCount' => $assignedCount,
            'message' => "{$assignedCount} kép sikeresen párosítva.",
        ]);
    }

    /**
     * Move photos to talon (skip matching).
     */
    public function assignToTalon(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $validator = Validator::make($request->all(), [
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ], [
            'mediaIds.required' => 'Legalább egy kép ID megadása kötelező.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        $movedCount = $photoService->moveToTalon(
            $project,
            array_map('intval', $request->input('mediaIds'))
        );

        return response()->json([
            'success' => true,
            'movedCount' => $movedCount,
            'message' => "{$movedCount} kép átmozgatva a talonba.",
        ]);
    }

    /**
     * Upload photo for a specific missing person.
     */
    public function uploadPersonPhoto(int $projectId, int $personId, Request $request): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $person = $project->missingPersons()->find($personId);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'A személy nem található.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
        ], [
            'photo.required' => 'Kép megadása kötelező.',
            'photo.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photo.max' => 'Maximum fájlméret: 20MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        $media = $photoService->uploadPersonPhoto($person, $request->file('photo'));

        return response()->json([
            'success' => true,
            'message' => 'Kép sikeresen feltöltve.',
            'photo' => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'thumbUrl' => $media->getUrl('thumb'),
                'version' => $media->getCustomProperty('version'),
            ],
        ]);
    }

    /**
     * Get talon photos.
     */
    public function getTalonPhotos(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        /** @var \App\Services\PartnerPhotoService $photoService */
        $photoService = app(\App\Services\PartnerPhotoService::class);

        return response()->json([
            'photos' => $photoService->getTalonPhotos($project),
        ]);
    }

    // ============================================
    // SCHOOLS MANAGEMENT (Partner's Schools List)
    // ============================================

    /**
     * Get schools list with project counts for partner.
     * Uses partner_schools pivot table for partner-school linkage.
     */
    public function schools(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $tabloPartner = \App\Models\TabloPartner::find($partnerId);

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
        $tabloPartner = \App\Models\TabloPartner::find($partnerId);

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

    // ============================================
    // CONTACTS MANAGEMENT (Partner's Contacts List)
    // ============================================

    /**
     * Get contacts list with project info for partner.
     */
    public function contacts(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 18), 50);
        $search = $request->input('search');

        // Contacts that belong directly to partner
        $query = \App\Models\TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        // Search using centralized SearchService
        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        $contacts = $query->orderBy('name')->paginate($perPage);

        $contacts->getCollection()->transform(function ($contact) {
            // Get all projects with their school names
            $projects = $contact->projects;
            $projectIds = $projects->pluck('id')->toArray();
            $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
            $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();

            // Check if contact is primary for any project
            $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => $isPrimary,
                // New: multiple projects support
                'projectIds' => $projectIds,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility: first project
                'projectId' => $projectIds[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => $contact->call_count ?? 0,
                'smsCount' => $contact->sms_count ?? 0,
            ];
        });

        // Get contact limits for this partner
        $partner = auth()->user()->partner;
        $maxContacts = $partner?->getMaxContacts();
        $currentCount = \App\Models\TabloContact::where('partner_id', $partnerId)->count();

        // Add limits to pagination response
        $response = $contacts->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxContacts,
            'can_create' => $maxContacts === null || $currentCount < $maxContacts,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return response()->json($response);
    }

    /**
     * Create standalone contact (optionally linked to projects).
     */
    public function createStandaloneContact(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:tablo_projects,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:tablo_projects,id',
        ], [
            'name.required' => 'A név megadása kötelező.',
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
            'project_id.exists' => 'A megadott projekt nem található.',
            'project_ids.*.exists' => 'A megadott projekt nem található.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create contact with partner_id
        $contact = \App\Models\TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        // Get project IDs (support both single and array)
        $projectIds = $request->input('project_ids', []);
        if ($request->filled('project_id') && empty($projectIds)) {
            $projectIds = [$request->input('project_id')];
        }

        // Verify all projects belong to partner and attach
        if (!empty($projectIds)) {
            $projectIds = array_map('intval', $projectIds);
            $validProjects = TabloProject::whereIn('id', $projectIds)
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->toArray();

            foreach ($validProjects as $projectId) {
                $contact->projects()->attach($projectId, ['is_primary' => false]);
            }
        }

        $contact->load('projects.school');

        // Build response
        $projects = $contact->projects;
        $projectIdsResult = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen létrehozva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => false,
                // New: multiple projects
                'projectIds' => $projectIdsResult,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility
                'projectId' => $projectIdsResult[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => 0,
                'smsCount' => 0,
            ],
        ], 201);
    }

    /**
     * Update standalone contact (can change projects).
     */
    public function updateStandaloneContact(Request $request, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Find contact that belongs to this partner
        $contact = \App\Models\TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:tablo_projects,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:tablo_projects,id',
        ], [
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
            'project_id.exists' => 'A megadott projekt nem található.',
            'project_ids.*.exists' => 'A megadott projekt nem található.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update basic fields
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        // Handle project sync if provided
        if ($request->has('project_ids') || $request->has('project_id')) {
            $projectIds = $request->input('project_ids', []);
            if ($request->filled('project_id') && empty($projectIds)) {
                $projectIds = [$request->input('project_id')];
            }

            // Verify all projects belong to partner
            $projectIds = array_map('intval', $projectIds);
            $validProjects = TabloProject::whereIn('id', $projectIds)
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->toArray();

            // Sync projects (this will detach old and attach new)
            // Preserve is_primary flags for existing relationships
            $syncData = [];
            foreach ($validProjects as $projectId) {
                $existingPivot = $contact->projects()->where('tablo_projects.id', $projectId)->first()?->pivot;
                $syncData[$projectId] = ['is_primary' => $existingPivot?->is_primary ?? false];
            }
            $contact->projects()->sync($syncData);
        }

        $contact->load('projects.school');

        // Build response
        $projects = $contact->projects;
        $projectIdsResult = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();
        $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => $isPrimary,
                // New: multiple projects
                'projectIds' => $projectIdsResult,
                'projectNames' => $projectNames,
                'schoolNames' => $schoolNames,
                // Backward compatibility
                'projectId' => $projectIdsResult[0] ?? null,
                'projectName' => $projectNames[0] ?? null,
                'schoolName' => $schoolNames[0] ?? null,
                'callCount' => $contact->call_count ?? 0,
                'smsCount' => $contact->sms_count ?? 0,
            ],
        ]);
    }

    /**
     * Delete standalone contact.
     */
    public function deleteStandaloneContact(int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        // Find contact that belongs to this partner
        $contact = \App\Models\TabloContact::where('partner_id', $partnerId)
            ->findOrFail($contactId);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }

    /**
     * Get projects for autocomplete (contact modal).
     */
    public function projectsAutocomplete(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $search = $request->input('search');

        $query = TabloProject::with('school')
            ->where('partner_id', $partnerId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('class_name', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('school', function ($sq) use ($search) {
                        $sq->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->limit(30)->get();

        return response()->json(
            $projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
            ])
        );
    }
}
