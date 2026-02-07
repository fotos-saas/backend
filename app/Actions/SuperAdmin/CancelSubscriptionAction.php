<?php

namespace App\Actions\SuperAdmin;

use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\SuperAdmin\SubscriberService;
use App\Services\SuperAdmin\SuperAdminStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Előfizetés lemondása (azonnal vagy periódus végén).
 *
 * Stripe lemondás + DB státusz frissítés + audit log.
 */
class CancelSubscriptionAction
{
    public function __construct(
        private readonly SubscriberService $subscriberService,
        private readonly SuperAdminStripeService $stripeService,
    ) {}

    public function execute(Request $request, Partner $partner, array $validated): JsonResponse
    {
        // Stripe lemondás
        $stripeResult = $this->stripeService->cancelSubscription($partner, $validated['immediate']);

        if (! $stripeResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $stripeResult['error'],
            ], 500);
        }

        // Partner státusz frissítése
        $this->subscriberService->updateCancelStatus($partner, $validated['immediate']);

        // Audit log
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
}
