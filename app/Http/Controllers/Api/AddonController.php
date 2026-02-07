<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Addon\AddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * AddonController
 *
 * Partner addon kezelés API:
 * - Elérhető addonok listája
 * - Aktív addonok lekérdezése
 * - Addon aktiválása (Stripe előfizetés)
 * - Addon lemondása
 */
class AddonController extends Controller
{
    public function __construct(
        private readonly AddonService $addonService,
    ) {}

    /**
     * GET /api/addons
     *
     * Elérhető addonok listája a partnerhez.
     */
    public function index(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        $addons = $this->addonService->getAvailableAddons($partner);

        return response()->json([
            'addons' => array_values($addons),
            'plan' => $partner->plan,
            'billing_cycle' => $partner->billing_cycle,
        ]);
    }

    /**
     * GET /api/addons/active
     *
     * Partner aktív addonjai.
     */
    public function active(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        $activeAddons = $this->addonService->getActiveAddons($partner);

        return response()->json([
            'addons' => $activeAddons,
        ]);
    }

    /**
     * POST /api/addons/{key}/subscribe
     *
     * Addon aktiválása (hozzáadás a Stripe előfizetéshez).
     */
    public function subscribe(Request $request, string $key): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        // Ingyenes addonokhoz nem kell Stripe előfizetés
        $addonConfig = config("plans.addons.{$key}");
        $isFree = $addonConfig['free'] ?? false;

        if (! $isFree && ! $partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Aktív előfizetés szükséges az addon vásárlásához.',
            ], 400);
        }

        try {
            $subscriptionItemId = $this->addonService->subscribe($partner, $key);

            Log::info('Addon subscribed via API', [
                'partner_id' => $partner->id,
                'user_id' => $request->user()->id,
                'addon_key' => $key,
            ]);

            return response()->json([
                'message' => 'Addon sikeresen aktiválva.',
                'subscription_item_id' => $subscriptionItemId,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe error during addon subscription', [
                'partner_id' => $partner->id,
                'addon_key' => $key,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Fizetési hiba történt. Kérjük, próbálja újra később.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * DELETE /api/addons/{key}
     *
     * Addon lemondása.
     */
    public function cancel(Request $request, string $key): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        try {
            $this->addonService->cancel($partner, $key);

            Log::info('Addon canceled via API', [
                'partner_id' => $partner->id,
                'user_id' => $request->user()->id,
                'addon_key' => $key,
            ]);

            return response()->json([
                'message' => 'Addon sikeresen lemondva.',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe error during addon cancellation', [
                'partner_id' => $partner->id,
                'addon_key' => $key,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Fizetési hiba történt. Kérjük, próbálja újra később.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
