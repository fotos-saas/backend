<?php

namespace App\Actions\SuperAdmin;

use App\DTOs\CreateDiscountData;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\Subscription\SubscriptionDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Kedvezmény beállítása előfizetőnek.
 *
 * DTO-ból létrehozza a kedvezményt a SubscriptionDiscountService-en keresztül,
 * majd audit logot rögzít.
 */
class SetDiscountAction
{
    public function __construct(
        private readonly SubscriptionDiscountService $discountService,
    ) {}

    public function execute(Request $request, Partner $partner): JsonResponse
    {
        try {
            $data = CreateDiscountData::fromRequest($request, $partner->id);
            $discount = $this->discountService->apply($data);

            // Audit log
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
