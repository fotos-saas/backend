<?php

namespace App\Actions\SuperAdmin;

use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\SuperAdmin\SuperAdminStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Előfizető terhelése Stripe számlával.
 *
 * Validálja a partnert és a Stripe customer ID-t,
 * majd a SuperAdminStripeService-en keresztül terheli.
 */
class ChargeSubscriberAction
{
    public function __construct(
        private readonly SuperAdminStripeService $stripeService,
    ) {}

    public function execute(Request $request, Partner $partner, array $validated): JsonResponse
    {
        // Stripe customer ID ellenőrzés
        if (! $partner->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'A partnernek nincs Stripe customer ID-ja.',
            ], 400);
        }

        // Stripe terhelés végrehajtása
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

        // Audit log
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
}
