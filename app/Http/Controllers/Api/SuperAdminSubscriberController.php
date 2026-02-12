<?php

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CancelSubscriptionAction;
use App\Actions\SuperAdmin\ChangePlanAction;
use App\Actions\SuperAdmin\ChargeSubscriberAction;
use App\Actions\SuperAdmin\RemoveDiscountAction;
use App\Actions\SuperAdmin\SetDiscountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\CancelSubscriptionRequest;
use App\Http\Requests\Api\SuperAdmin\ChangePlanRequest;
use App\Http\Requests\Api\SuperAdmin\ChargeSubscriberRequest;
use App\Http\Requests\Api\SuperAdmin\SetDiscountRequest;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Services\SuperAdmin\SubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin Subscriber Controller.
 *
 * Subscriber listing, details, billing, plan changes, discounts, audit logs.
 * Üzleti logika az Actions/SuperAdmin/ mappában.
 */
class SuperAdminSubscriberController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscriberService,
    ) {}

    /**
     * Előfizetők listázása szűrőkkel és lapozással.
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
     * Egy előfizető részletes adatai.
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

        // Megtekintés audit log
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
     * Előfizető terhelése Stripe számlával.
     */
    public function chargeSubscriber(ChargeSubscriberRequest $request, int $id, ChargeSubscriberAction $action): JsonResponse
    {
        $validated = $request->validated();

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        return $action->execute($request, $partner, $validated);
    }

    /**
     * Előfizető csomagjának módosítása.
     */
    public function changePlan(ChangePlanRequest $request, int $id, ChangePlanAction $action): JsonResponse
    {
        $validated = $request->validated();

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        return $action->execute($request, $partner, $validated);
    }

    /**
     * Előfizetés lemondása.
     */
    public function cancelSubscription(CancelSubscriptionRequest $request, int $id, CancelSubscriptionAction $action): JsonResponse
    {
        $validated = $request->validated();

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        return $action->execute($request, $partner, $validated);
    }

    /**
     * Előfizető audit logjainak lekérése.
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
     * Kedvezmény beállítása előfizetőnek.
     */
    public function setDiscount(
        SetDiscountRequest $request,
        int $id,
        SetDiscountAction $action,
    ): JsonResponse {
        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        return $action->execute($request, $partner);
    }

    /**
     * Kedvezmény eltávolítása előfizetőtől.
     */
    public function removeDiscount(
        Request $request,
        int $id,
        RemoveDiscountAction $action,
    ): JsonResponse {
        $partner = Partner::with('activeDiscount')->find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        return $action->execute($request, $partner);
    }
}
