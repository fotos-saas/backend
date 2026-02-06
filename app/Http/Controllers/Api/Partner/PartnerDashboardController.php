<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\QrRegistrationCode;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Services\Search\SearchService;
use App\Helpers\QueryHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Partner Dashboard Controller - Statistics and project listings.
 *
 * Handles: stats(), projects(), projectDetails(), projectsAutocomplete()
 */
class PartnerDashboardController extends Controller
{
    use PartnerAuthTrait;

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

        // Get project limits for this partner (csapattagoknak is működik)
        $partner = auth()->user()->getEffectivePartner();
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
                'finalizedAt' => $project->data['finalized_at'] ?? null,
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
            'gallery',
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

        $activeQrCodes = $project->qrCodes->where('is_active', true);
        $activeQrCode = $activeQrCodes->first();
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
            'statusColor' => $project->status?->tailwindColor() ?? 'gray',
            'tabloStatus' => $project->tabloStatus?->toApiResponse(),
            'photoDate' => $project->photo_date?->format('Y-m-d'),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'expectedClassSize' => $project->expected_class_size,
            'finalizedAt' => $project->data['finalized_at'] ?? null,
            'draftPhotoCount' => $project->getMedia('tablo_pending')->count(),
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
                'type' => $activeQrCode->type?->value ?? 'coordinator',
                'typeLabel' => $activeQrCode->type?->label() ?? 'Kapcsolattartó',
                'usageCount' => $activeQrCode->usage_count,
                'maxUsages' => $activeQrCode->max_usages,
                'expiresAt' => $activeQrCode->expires_at?->toIso8601String(),
                'isValid' => $activeQrCode->isValid(),
                'registrationUrl' => $activeQrCode->getRegistrationUrl(),
            ] : null,
            'activeQrCodes' => $activeQrCodes->values()->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'type' => $qr->type?->value ?? 'coordinator',
                'typeLabel' => $qr->type?->label() ?? 'Kapcsolattartó',
                'usageCount' => $qr->usage_count,
                'isValid' => $qr->isValid(),
                'registrationUrl' => $qr->getRegistrationUrl(),
            ]),
            'qrCodesHistory' => $project->qrCodes->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'type' => $qr->type?->value ?? 'coordinator',
                'typeLabel' => $qr->type?->label() ?? 'Kapcsolattartó',
                'isActive' => $qr->is_active,
                'usageCount' => $qr->usage_count,
                'createdAt' => $qr->created_at->toIso8601String(),
            ]),
            'tabloGalleryId' => $project->tablo_gallery_id,
            'galleryPhotosCount' => $project->gallery?->getMedia('photos')->count() ?? 0,
            'createdAt' => $project->created_at->toIso8601String(),
            'updatedAt' => $project->updated_at->toIso8601String(),
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
                $q->where('class_name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhere('name', 'ILIKE', QueryHelper::safeLikePattern($search))
                    ->orWhereHas('school', function ($sq) use ($search) {
                        $sq->where('name', 'ILIKE', QueryHelper::safeLikePattern($search));
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

    /**
     * Get order data for a project (partner view).
     */
    public function getProjectOrderData(int $projectId): JsonResponse
    {
        $tabloProject = $this->getProjectForPartner($projectId);
        $tabloProject->load(['school', 'contacts']);

        $data = $tabloProject->data ?? [];

        $hasOrderData = ! empty($data['description'])
            || ! empty($data['student_description'])
            || ! empty($data['teacher_description'])
            || ! empty($data['order_form']);

        if (! $hasOrderData) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Nincs még leadott megrendelés',
            ]);
        }

        $pdfUrl = null;
        if (! empty($data['order_form'])) {
            $pdfUrl = 'https://api.tablokiraly.hu/storage/'.$data['order_form'];
        }

        $studentCount = ! empty($data['student_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['student_description'])))
            : null;
        $teacherCount = ! empty($data['teacher_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['teacher_description'])))
            : null;

        $orderAnalysis = $tabloProject->latestOrderAnalysis;
        $aiSummary = $orderAnalysis?->ai_summary;
        $tags = $orderAnalysis?->tags ?? [];

        $contact = $tabloProject->contacts->first();

        return response()->json([
            'success' => true,
            'data' => [
                'contactName' => $contact?->name,
                'contactPhone' => $contact?->phone,
                'contactEmail' => $contact?->email,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'studentCount' => $studentCount,
                'teacherCount' => $teacherCount,
                'color' => $data['color'] ?? null,
                'fontFamily' => $data['font_family'] ?? null,
                'sortType' => $data['sort_type'] ?? null,
                'description' => $data['description'] ?? null,
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,
                'quote' => $data['quote'] ?? null,
                'aiSummary' => $aiSummary,
                'tags' => $tags,
                'pdfUrl' => $pdfUrl,
                'orderDate' => $data['original_created_at'] ?? $tabloProject->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Generate and view order PDF for a project (partner view).
     */
    public function viewProjectOrderPdf(int $projectId): JsonResponse
    {
        $tabloProject = $this->getProjectForPartner($projectId);
        $tabloProject->load(['school', 'contacts']);

        if (! $tabloProject->hasOrderData()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs leadott megrendelés ehhez a projekthez',
            ], 404);
        }

        try {
            $projectData = $tabloProject->data ?? [];
            $contact = $tabloProject->contacts->first();

            $pdfData = [
                'name' => $contact?->name ?? $tabloProject->name ?? '',
                'contactEmail' => $contact?->email ?? '',
                'contactPhone' => $contact?->phone ?? '',
                'schoolName' => $tabloProject->school?->name ?? $tabloProject->school_name ?? '',
                'schoolCity' => $tabloProject->school?->city ?? $tabloProject->school_city ?? '',
                'className' => $tabloProject->class_name ?? '',
                'classYear' => $tabloProject->class_year ?? '',
                'quote' => $projectData['quote'] ?? '',
                'fontFamily' => $projectData['font_family'] ?? '',
                'color' => $projectData['color'] ?? '#000000',
                'description' => $projectData['description'] ?? '',
                'sortType' => $projectData['sort_type'] ?? 'abc',
                'studentDescription' => $projectData['student_description'] ?? '',
                'teacherDescription' => $projectData['teacher_description'] ?? '',
            ];

            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            $filename = sprintf('order-view-%d-%s.pdf', $projectId, now()->format('YmdHis'));
            $path = 'tablo-projects/' . $projectId . '/views/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            $pdfUrl = config('app.url') . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'pdfUrl' => $pdfUrl,
                'message' => 'Megrendelőlap sikeresen elkészítve!',
            ]);
        } catch (\Exception $e) {
            Log::error('Partner order PDF view generation failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt az előnézet generálásakor',
            ], 500);
        }
    }
}
