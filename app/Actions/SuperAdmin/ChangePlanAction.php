<?php

namespace App\Actions\SuperAdmin;

use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\SuperAdmin\SubscriberService;
use App\Services\SuperAdmin\SuperAdminStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Előfizető csomagjának módosítása.
 *
 * DB tranzakcióban: először Stripe frissítés, majd DB frissítés.
 * Audit logot is rögzít a csomag váltásról.
 */
class ChangePlanAction
{
    public function __construct(
        private readonly SubscriberService $subscriberService,
        private readonly SuperAdminStripeService $stripeService,
    ) {}

    public function execute(Request $request, Partner $partner, array $validated): JsonResponse
    {
        $oldPlan = $partner->plan;
        $oldBillingCycle = $partner->billing_cycle;
        $newPlan = $validated['plan'];
        $newBillingCycle = $validated['billing_cycle'] ?? $partner->billing_cycle ?? 'monthly';

        // Stripe price ID lekérése
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
}
