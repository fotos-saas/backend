<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateDiscountData;
use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\SetDiscountRequest;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Models\QrRegistrationCode;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Services\Subscription\SubscriptionDiscountService;
use App\Services\SuperAdmin\SubscriberService;
use App\Services\SuperAdmin\SuperAdminStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Super Admin Controller for frontend-tablo super admin dashboard.
 *
 * Provides system-wide statistics and management for super admin users.
 */
class SuperAdminController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscriberService,
        private readonly SuperAdminStripeService $stripeService,
    ) {}

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
     * List subscribers (Partner model) with pagination, search and filters.
     */
    public function subscribers(Request $request): JsonResponse
    {
        $subscribers = $this->subscriberService->getFilteredList($request);

        return response()->json([
            'data' => $subscribers->map(fn ($partner) => $this->subscriberService->formatForList($partner)),
            'current_page' => $subscribers->currentPage(),
            'last_page' => $subscribers->lastPage(),
            'per_page' => $subscribers->perPage(),
            'total' => $subscribers->total(),
            'from' => $subscribers->firstItem(),
            'to' => $subscribers->lastItem(),
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
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registrationEnabled' => 'sometimes|boolean',
            'trialDays' => 'sometimes|integer|min:0|max:90',
            'defaultPlan' => 'sometimes|string|in:alap,iskola,studio',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Beállítások mentve.',
        ]);
    }

    /**
     * Get single subscriber details.
     */
    public function getSubscriber(Request $request, int $id): JsonResponse
    {
        $partner = Partner::with(['user', 'activeDiscount'])->find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        // Log view action
        AdminAuditLog::log(
            $request->user()->id,
            $partner->id,
            AdminAuditLog::ACTION_VIEW,
            null,
            $request->ip()
        );

        return response()->json($this->subscriberService->formatForDetail($partner));
    }

    /**
     * Charge subscriber with Stripe Invoice.
     */
    public function chargeSubscriber(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        if (! $partner->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'A partnernek nincs Stripe customer ID-ja.',
            ], 400);
        }

        $result = $this->stripeService->chargePartner(
            $partner,
            $validated['amount'],
            $validated['description']
        );

        if (! $result['success']) {
            $statusCode = str_contains($result['error'] ?? '', 'formátum') ? 400 : 500;

            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], $statusCode);
        }

        // Log audit
        AdminAuditLog::log(
            $request->user()->id,
            $partner->id,
            AdminAuditLog::ACTION_CHARGE,
            [
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'stripe_invoice_id' => $result['invoiceId'],
            ],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Számla sikeresen létrehozva és terhelve.',
            'invoiceId' => $result['invoiceId'],
        ]);
    }

    /**
     * Change subscriber plan.
     */
    public function changePlan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:alap,iskola,studio',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        $oldPlan = $partner->plan;
        $oldBillingCycle = $partner->billing_cycle;
        $newPlan = $validated['plan'];
        $newBillingCycle = $validated['billing_cycle'] ?? $partner->billing_cycle ?? 'monthly';

        // Get new Stripe price ID
        $newPriceId = config("stripe.prices.{$newPlan}.{$newBillingCycle}");

        if (empty($newPriceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen csomag vagy számlázási ciklus.',
            ], 400);
        }

        try {
            return DB::transaction(function () use ($request, $partner, $newPlan, $newBillingCycle, $newPriceId, $oldPlan, $oldBillingCycle) {
                // 1. ELŐSZÖR a Stripe-ot frissítjük
                $stripeResult = $this->stripeService->changePlan($partner, $newPriceId);

                if (! $stripeResult['success']) {
                    throw new \Exception($stripeResult['error']);
                }

                // 2. AZUTÁN a DB-t frissítjük
                $this->subscriberService->updatePlan($partner, $newPlan, $newBillingCycle);

                // 3. Audit log
                AdminAuditLog::log(
                    $request->user()->id,
                    $partner->id,
                    AdminAuditLog::ACTION_CHANGE_PLAN,
                    [
                        'old_plan' => $oldPlan,
                        'old_billing_cycle' => $oldBillingCycle,
                        'new_plan' => $newPlan,
                        'new_billing_cycle' => $newBillingCycle,
                    ],
                    $request->ip()
                );

                $newPrice = $this->subscriberService->getPlanPrice($newPlan, $newBillingCycle);

                return response()->json([
                    'success' => true,
                    'message' => 'Csomag sikeresen módosítva.',
                    'newPrice' => $newPrice,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Plan change error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => str_contains($e->getMessage(), 'Stripe')
                    ? $e->getMessage()
                    : 'Hiba történt a csomag módosításakor.',
            ], 500);
        }
    }

    /**
     * Cancel subscriber subscription.
     */
    public function cancelSubscription(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'immediate' => 'required|boolean',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        $stripeResult = $this->stripeService->cancelSubscription($partner, $validated['immediate']);

        if (! $stripeResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $stripeResult['error'],
            ], 500);
        }

        // Update partner status
        $this->subscriberService->updateCancelStatus($partner, $validated['immediate']);

        // Log audit
        AdminAuditLog::log(
            $request->user()->id,
            $partner->id,
            AdminAuditLog::ACTION_CANCEL_SUBSCRIPTION,
            ['immediate' => $validated['immediate']],
            $request->ip()
        );

        $message = $validated['immediate']
            ? 'Előfizetés azonnal törölve.'
            : 'Előfizetés törölve a periódus végén.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Get audit logs for a subscriber.
     */
    public function getAuditLogs(Request $request, int $id): JsonResponse
    {
        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        $logs = $this->subscriberService->getAuditLogs($id, $request);

        return response()->json([
            'data' => $logs->map(fn ($log) => $this->subscriberService->formatAuditLog($log)),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }

    /**
     * Set discount for subscriber.
     */
    public function setDiscount(
        SetDiscountRequest $request,
        int $id,
        SubscriptionDiscountService $discountService,
    ): JsonResponse {
        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        try {
            $data = CreateDiscountData::fromRequest($request, $id);
            $discount = $discountService->apply($data);

            AdminAuditLog::log(
                $request->user()->id,
                $partner->id,
                AdminAuditLog::ACTION_SET_DISCOUNT,
                [
                    'percent' => $data->percent,
                    'duration_months' => $data->durationMonths,
                    'note' => $data->note,
                ],
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => "{$data->percent}% kedvezmény beállítva.",
                'discount' => [
                    'percent' => $discount->percent,
                    'validUntil' => $discount->valid_until?->toIso8601String(),
                    'note' => $discount->note,
                    'createdAt' => $discount->created_at?->toIso8601String(),
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe discount error', [
                'partner_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba történt.',
            ], 502);
        }
    }

    /**
     * Remove discount from subscriber.
     */
    public function removeDiscount(
        Request $request,
        int $id,
        SubscriptionDiscountService $discountService,
    ): JsonResponse {
        $partner = Partner::with('activeDiscount')->find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        if (! $partner->activeDiscount) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs aktív kedvezmény.',
            ], 400);
        }

        try {
            $oldPercent = $partner->activeDiscount->percent;
            $discountService->remove($partner);

            AdminAuditLog::log(
                $request->user()->id,
                $partner->id,
                AdminAuditLog::ACTION_REMOVE_DISCOUNT,
                ['old_percent' => $oldPercent],
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Kedvezmény eltávolítva.',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe remove discount error', [
                'partner_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba történt.',
            ], 502);
        }
    }
}
