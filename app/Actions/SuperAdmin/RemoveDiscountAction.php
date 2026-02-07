<?php

namespace App\Actions\SuperAdmin;

use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\Subscription\SubscriptionDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Kedvezmény eltávolítása előfizetőtől.
 *
 * Ellenőrzi, hogy van-e aktív kedvezmény, majd a
 * SubscriptionDiscountService-en keresztül törli + audit log.
 */
class RemoveDiscountAction
{
    public function __construct(
        private readonly SubscriptionDiscountService $discountService,
    ) {}

    public function execute(Request $request, Partner $partner): JsonResponse
    {
        // Aktív kedvezmény ellenőrzés
        if (! $partner->activeDiscount) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs aktív kedvezmény.',
            ], 400);
        }

        try {
            $oldPercent = $partner->activeDiscount->percent;
            $this->discountService->remove($partner);

            // Audit log
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
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba történt.',
            ], 502);
        }
    }
}
