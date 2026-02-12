<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPartner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SetStorageAddonRequest;
use App\Services\Storage\StorageAddonService;
use App\Services\Storage\StorageUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * StorageController
 *
 * Partner tárhely kezelés API:
 * - Tárhely használat lekérdezése
 * - Extra tárhely vásárlás (Stripe addon)
 * - Extra tárhely eltávolítása
 */
class StorageController extends Controller
{
    use ResolvesPartner;
    public function __construct(
        private readonly StorageUsageService $usageService,
        private readonly StorageAddonService $addonService,
    ) {}

    /**
     * GET /api/storage/usage
     *
     * Partner tárhely használat és árak lekérdezése.
     */
    public function usage(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user()->id);

        if (! $partner) {
            return response()->json([
                'message' => 'Inaktív fiók. Kérjük, jelentkezz be újra.',
                'code' => 'no_partner',
            ], 403);
        }

        $stats = $this->usageService->getUsageStats($partner);

        return response()->json([
            ...$stats,
            'addon_price_monthly' => $this->addonService->getMonthlyPrice(),
            'addon_price_yearly' => $this->addonService->getYearlyPrice(),
            'billing_cycle' => $partner->billing_cycle,
        ]);
    }

    /**
     * POST /api/storage/addon
     *
     * Extra tárhely beállítása/módosítása.
     * Body: { "gb": 10 }
     */
    public function setAddon(SetStorageAddonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $partner = $this->resolvePartner($request->user()->id);

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        if (! $partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Aktív előfizetés szükséges az extra tárhely vásárlásához.',
            ], 400);
        }

        try {
            $this->addonService->setAddonGb($partner, $validated['gb']);

            $partner->refresh();

            Log::info('Storage addon updated via API', [
                'partner_id' => $partner->id,
                'user_id' => $request->user()->id,
                'gb' => $validated['gb'],
            ]);

            $actionText = $validated['gb'] > 0 ? 'beállítva' : 'eltávolítva';

            return response()->json([
                'message' => "Extra tárhely sikeresen {$actionText}.",
                'additional_gb' => $partner->additional_storage_gb,
                'total_limit_gb' => $partner->getTotalStorageLimitGb(),
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe error during storage addon update', [
                'partner_id' => $partner->id,
                'error' => 'Hiba történt a művelet során.',
            ]);

            return response()->json([
                'message' => 'Fizetési hiba történt. Kérjük, próbálja újra később.',
            ], 500);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * DELETE /api/storage/addon
     *
     * Extra tárhely eltávolítása (0 GB-ra állítás).
     */
    public function removeAddon(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user()->id);

        if (! $partner) {
            return response()->json([
                'message' => 'Partner fiók nem található.',
            ], 404);
        }

        if (! $partner->additional_storage_gb || $partner->additional_storage_gb === 0) {
            return response()->json([
                'message' => 'Nincs extra tárhely a fiókhoz.',
            ], 400);
        }

        // Ellenőrizzük, hogy a jelenlegi használat belefér-e a plan limitbe
        $currentUsageGb = $this->usageService->getUsageGb($partner);
        $planLimitGb = $partner->getPlanStorageLimitGb();

        if ($currentUsageGb > $planLimitGb) {
            return response()->json([
                'message' => "Az extra tárhely nem távolítható el, mert a jelenlegi használat ({$currentUsageGb} GB) meghaladja a csomag limitjét ({$planLimitGb} GB). Töröljön fájlokat az eltávolítás előtt.",
            ], 400);
        }

        try {
            $this->addonService->removeAddon($partner);

            $partner->refresh();

            Log::info('Storage addon removed via API', [
                'partner_id' => $partner->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Extra tárhely sikeresen eltávolítva.',
                'additional_gb' => 0,
                'total_limit_gb' => $partner->getTotalStorageLimitGb(),
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe error during storage addon removal', [
                'partner_id' => $partner->id,
                'error' => 'Hiba történt a művelet során.',
            ]);

            return response()->json([
                'message' => 'Fizetési hiba történt. Kérjük, próbálja újra később.',
            ], 500);
        }
    }
}
