<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\MatchMissingPersonRequest;
use App\Http\Requests\Api\SuperAdmin\UpdateSystemSettingsRequest;
use App\Models\Partner;
use App\Models\QrRegistrationCode;
use App\Models\TabloPartner;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin Controller for frontend-tablo super admin dashboard.
 *
 * Dashboard statistics, partner listing, system settings.
 */
class SuperAdminController extends Controller
{
    /**
     * Dashboard statistics.
     *
     * OPTIMALIZÁCIÓ: 5 perces cache + kombinált query-k
     */
    public function stats(): JsonResponse
    {
        return response()->json(
            cache()->remember('super-admin:stats', now()->addMinutes(5), function () {
                // Partner stats egyetlen query-ben
                $partnerStats = Partner::selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active
                ")->first();

                return [
                    'totalPartners' => TabloPartner::count(),
                    'totalProjects' => TabloProject::count(),
                    'activeQrCodes' => QrRegistrationCode::active()->count(),
                    'projectsByStatus' => TabloProject::selectRaw('status, COUNT(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray(),
                    'totalSubscribers' => (int) $partnerStats->total,
                    'activeSubscribers' => (int) $partnerStats->active,
                ];
            })
        );
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
            $safePattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($safePattern) {
                $q->where('name', 'ilike', $safePattern)
                    ->orWhere('email', 'ilike', $safePattern);
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
                'schoolName' => $partner->email,
                'hasActiveQrCode' => false,
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
                'publicKey' => config('services.stripe.key') ? substr(config('services.stripe.key'), 0, 12).'***' : null,
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
    public function updateSettings(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'message' => 'Beállítások mentve.',
        ]);
    }

    /**
     * Manual photo matching for missing persons (super admin only).
     */
    public function matchMissingPerson(MatchMissingPersonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $person = TabloPerson::findOrFail($validated['person_id']);
        $person->update(['media_id' => $validated['media_id']]);

        return response()->json(['success' => true]);
    }
}
