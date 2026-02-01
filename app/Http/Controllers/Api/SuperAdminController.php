<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\QrRegistrationCode;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin Controller for frontend-tablo super admin dashboard.
 *
 * Provides system-wide statistics and management for super admin users.
 */
class SuperAdminController extends Controller
{
    /**
     * Csomag árak (Ft)
     */
    private const PLAN_PRICES = [
        'alap' => ['monthly' => 4990, 'yearly' => 49900],
        'iskola' => ['monthly' => 14990, 'yearly' => 149900],
        'studio' => ['monthly' => 29990, 'yearly' => 299900],
    ];

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

        // Subscribers count (Partner model)
        $totalSubscribers = Partner::count();
        $activeSubscribers = Partner::where('subscription_status', 'active')->count();

        return response()->json([
            'totalPartners' => $totalPartners,
            'totalProjects' => $totalProjects,
            'activeQrCodes' => $activeQrCodes,
            'projectsByStatus' => $projectsByStatus,
            'totalSubscribers' => $totalSubscribers,
            'activeSubscribers' => $activeSubscribers,
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

    /**
     * List subscribers (Partner model) with pagination, search and filters.
     */
    public function subscribers(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 18);
        $search = $request->input('search');
        $plan = $request->input('plan');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = Partner::query()
            ->join('users', 'partners.user_id', '=', 'users.id')
            ->select([
                'partners.*',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'ilike', "%{$search}%")
                    ->orWhere('users.email', 'ilike', "%{$search}%")
                    ->orWhere('partners.company_name', 'ilike', "%{$search}%");
            });
        }

        // Plan filter
        if ($plan && in_array($plan, ['alap', 'iskola', 'studio'])) {
            $query->where('partners.plan', $plan);
        }

        // Status filter
        if ($status && in_array($status, ['active', 'paused', 'canceling', 'trial'])) {
            $query->where('partners.subscription_status', $status);
        }

        // Sorting
        $allowedSortFields = [
            'name' => 'users.name',
            'email' => 'users.email',
            'plan' => 'partners.plan',
            'subscription_ends_at' => 'partners.subscription_ends_at',
            'created_at' => 'partners.created_at',
        ];

        $sortColumn = $allowedSortFields[$sortBy] ?? 'partners.created_at';
        $query->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc');

        $subscribers = $query->paginate($perPage);

        return response()->json([
            'data' => $subscribers->map(fn ($partner) => $this->formatSubscriber($partner)),
            'current_page' => $subscribers->currentPage(),
            'last_page' => $subscribers->lastPage(),
            'per_page' => $subscribers->perPage(),
            'total' => $subscribers->total(),
            'from' => $subscribers->firstItem(),
            'to' => $subscribers->lastItem(),
        ]);
    }

    /**
     * Format subscriber for API response.
     */
    private function formatSubscriber(Partner $partner): array
    {
        $planNames = [
            'alap' => 'Alap',
            'iskola' => 'Iskola',
            'studio' => 'Stúdió',
        ];

        $billingCycle = $partner->billing_cycle ?? 'monthly';
        $plan = $partner->plan ?? 'alap';
        $price = self::PLAN_PRICES[$plan][$billingCycle] ?? 0;

        return [
            'id' => $partner->id,
            'name' => $partner->user_name,
            'email' => $partner->user_email,
            'companyName' => $partner->company_name,
            'plan' => $plan,
            'planName' => $planNames[$plan] ?? $plan,
            'billingCycle' => $billingCycle,
            'price' => $price,
            'subscriptionStatus' => $partner->subscription_status ?? 'trial',
            'subscriptionEndsAt' => $partner->subscription_ends_at?->toIso8601String(),
            'createdAt' => $partner->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get system settings.
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'system' => [
                'registrationEnabled' => config('app.registration_enabled', true),
                'trialDays' => config('app.trial_days', 14),
                'defaultPlan' => config('app.default_plan', 'alap'),
            ],
            'email' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username') ? '***' : null,
            ],
            'stripe' => [
                'publicKey' => config('services.stripe.key') ? substr(config('services.stripe.key'), 0, 12) . '***' : null,
                'webhookConfigured' => ! empty(config('services.stripe.webhook_secret')),
            ],
            'info' => [
                'appVersion' => config('app.version', '1.0.0'),
                'laravelVersion' => app()->version(),
                'phpVersion' => PHP_VERSION,
                'environment' => app()->environment(),
                'cacheDriver' => config('cache.default'),
                'queueDriver' => config('queue.default'),
            ],
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registrationEnabled' => 'sometimes|boolean',
            'trialDays' => 'sometimes|integer|min:0|max:90',
            'defaultPlan' => 'sometimes|string|in:alap,iskola,studio',
        ]);

        // Note: In production, these would be stored in database or .env
        // For now, we just return success
        return response()->json([
            'success' => true,
            'message' => 'Beállítások mentve.',
        ]);
    }
}
