<?php

namespace App\Services\Partner;

use App\Helpers\QueryHelper;
use App\Models\QrRegistrationCode;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Models\TabloStatus;
use App\Services\Search\SearchService;
use Illuminate\Http\Request;

/**
 * Partner dashboard üzleti logika - statisztikák, projekt listák, keresés.
 *
 * Transzformáció: PartnerProjectTransformer
 * Megrendelés: PartnerOrderService
 */
class PartnerDashboardService
{
    /** Engedélyezett rendezési mezők */
    private const ALLOWED_SORT_FIELDS = [
        'created_at', 'photo_date', 'class_year',
        'school_name', 'tablo_status', 'missing_count', 'samples_count',
    ];

    public function __construct(
        private readonly PartnerProjectTransformer $transformer,
    ) {}

    /**
     * Dashboard statisztikák lekérése.
     */
    public function getStats(int $partnerId): array
    {
        $totalProjects = TabloProject::where('partner_id', $partnerId)->count();

        $activeQrCodes = QrRegistrationCode::active()
            ->whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->count();

        $totalSchools = TabloSchool::whereHas(
            'projects',
            fn ($q) => $q->where('partner_id', $partnerId)
        )->count();

        $upcomingPhotoshoots = TabloProject::where('partner_id', $partnerId)
            ->whereNotNull('photo_date')
            ->where('photo_date', '>=', now()->startOfDay())
            ->count();

        $projectsByStatus = TabloProject::where('partner_id', $partnerId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'totalProjects' => $totalProjects,
            'activeQrCodes' => $activeQrCodes,
            'totalSchools' => $totalSchools,
            'upcomingPhotoshoots' => $upcomingPhotoshoots,
            'projectsByStatus' => $projectsByStatus,
        ];
    }

    /**
     * Projektek listázása szűréssel, rendezéssel és transzformációval.
     */
    public function getProjectsList(int $partnerId, Request $request): array
    {
        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $status = $request->input('status');

        if (! in_array($sortBy, self::ALLOWED_SORT_FIELDS)) {
            $sortBy = 'created_at';
        }

        $query = $this->buildProjectsQuery($partnerId);
        $query = $this->applySearch($query, $search);
        $query = $this->applyFilters($query, $request, $status);
        $query = $this->applySorting($query, $sortBy, $sortDir);

        $projects = $query->paginate($perPage);

        // Projekt limitek lekérése
        $partner = auth()->user()->getEffectivePartner();
        $maxClasses = $partner?->getMaxClasses();
        $currentCount = TabloProject::where('partner_id', $partnerId)->count();

        // Transzformáció
        $projects->getCollection()->transform(
            fn ($project) => $this->transformer->toListItem($project)
        );

        $response = $projects->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxClasses,
            'can_create' => $maxClasses === null || $currentCount < $maxClasses,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return $response;
    }

    /**
     * Projekt részletek lekérése és transzformálása.
     */
    public function getProjectDetailsData(TabloProject $project): array
    {
        $project->load([
            'school',
            'partner',
            'contacts',
            'tabloStatus',
            'gallery',
            'qrCodes' => fn ($q) => $q->orderBy('created_at', 'desc'),
        ]);

        $project->loadCount([
            'guestSessions as guests_count' => fn ($q) => $q->where('is_banned', false),
            'persons as missing_count' => fn ($q) => $q->whereNull('media_id'),
        ]);

        return $this->transformer->toDetailResponse($project);
    }

    /**
     * Projektek autocomplete keresése (pl. kontakt modal-hoz).
     */
    public function getAutocompleteResults(int $partnerId, ?string $search): array
    {
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

        return $query->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
            ])
            ->toArray();
    }

    // ─── Privát segédmetódusok ─────────────────────────────

    /**
     * Projektek query összeállítása eager loading-gal.
     */
    private function buildProjectsQuery(int $partnerId): \Illuminate\Database\Eloquent\Builder
    {
        return TabloProject::with([
            'school',
            'contacts',
            'tabloStatus',
            'qrCodes' => fn ($q) => $q->active(),
            'media' => fn ($q) => $q->whereIn('collection_name', ['samples', 'tablo_pending']),
        ])
            ->withCount([
                'guestSessions as guests_count' => fn ($q) => $q->where('is_banned', false),
                'persons as missing_count' => fn ($q) => $q->whereNull('media_id'),
                'persons as missing_students_count' => fn ($q) => $q->whereNull('media_id')->where('type', 'student'),
                'persons as missing_teachers_count' => fn ($q) => $q->whereNull('media_id')->where('type', 'teacher'),
            ])
            ->where('partner_id', $partnerId);
    }

    /**
     * Keresés alkalmazása SearchService-szel.
     */
    private function applySearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return app(SearchService::class)->apply($query, $search, [
            'columns' => ['name', 'class_name'],
            'relations' => [
                'school' => ['name', 'city'],
                'contacts' => ['name', 'email'],
                'persons' => ['name'],
            ],
            'prefixes' => [
                '@' => ['contacts' => ['name', 'email']],
            ],
        ]);
    }

    /**
     * Szűrők alkalmazása (status, is_aware, has_draft).
     */
    private function applyFilters($query, Request $request, ?string $status)
    {
        if ($status) {
            $query->where('status', $status);
        }

        if ($request->filled('is_aware')) {
            $query->where('is_aware', $request->input('is_aware') === 'true');
        }

        if ($request->filled('has_draft')) {
            $hasDraft = $request->input('has_draft') === 'true';
            if ($hasDraft) {
                $query->whereHas('media', fn ($q) => $q->where('collection_name', 'tablo_pending'));
            } else {
                $query->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'tablo_pending'));
            }
        }

        return $query;
    }

    /**
     * Rendezés alkalmazása (subquery speciális mezőkhöz).
     */
    private function applySorting($query, string $sortBy, string $sortDir)
    {
        return match ($sortBy) {
            'school_name' => $query->orderBy(
                TabloSchool::select('name')
                    ->whereColumn('tablo_schools.id', 'tablo_projects.school_id')
                    ->limit(1),
                $sortDir
            ),
            'tablo_status' => $query->orderBy(
                TabloStatus::select('sort_order')
                    ->whereColumn('tablo_statuses.id', 'tablo_projects.tablo_status_id')
                    ->limit(1),
                $sortDir
            ),
            'missing_count' => $query->orderBy('missing_count', $sortDir),
            'samples_count' => $query->withCount('media as samples_count')->orderBy('samples_count', $sortDir),
            default => $query->orderBy($sortBy, $sortDir),
        };
    }
}
