<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateDiscountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\SetDiscountRequest;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\Subscription\SubscriptionDiscountService;
use App\Services\SuperAdmin\SubscriberService;
use App\Services\SuperAdmin\SuperAdminStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Super Admin Subscriber Controller.
 *
 * Subscriber listing, details, billing, plan changes, discounts, audit logs.
 */
class SuperAdminSubscriberController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscriberService,
        private readonly SuperAdminStripeService $stripeService,
    ) {}

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
