<?php

namespace App\Services\Addon;

use App\Models\Partner;
use App\Models\PartnerAddon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\SubscriptionItem;

/**
 * AddonService
 *
 * Addon előfizetések kezelése (Stripe integráció)
 */
class AddonService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Elérhető addonok listája a partnerhez
     *
     * @return array<string, array{key: string, name: string, description: string, monthlyPrice: int, yearlyPrice: int, isActive: bool, canPurchase: bool}>
     */
    public function getAvailableAddons(Partner $partner): array
    {
        $addons = [];
        $activeAddons = $partner->addons()->where('status', 'active')->pluck('addon_key')->toArray();
        $addonsConfig = config('plans.addons', []);

        foreach ($addonsConfig as $key => $addon) {
            $availableFor = $addon['available_for'] ?? [];

            // Az addon elérhető-e a partner csomagjához?
            $isAvailableForPlan = in_array($partner->plan, $availableFor);

            // Studio/VIP: ha az addon feature-jei benne vannak a csomagban
            $isIncludedInPlan = false;
            $planFeatures = config("plans.plans.{$partner->plan}.feature_keys", []);
            foreach ($addon['includes'] ?? [] as $feature) {
                if (in_array($feature, $planFeatures)) {
                    $isIncludedInPlan = true;
                    break;
                }
            }

            // Csak akkor jelenik meg, ha elérhető addonként VAGY csomagban benne van
            if (! $isAvailableForPlan && ! $isIncludedInPlan) {
                continue;
            }

            $canPurchase = $isAvailableForPlan && ! in_array($key, $activeAddons) && ! $isIncludedInPlan;

            $addons[$key] = [
                'key' => $key,
                'name' => $addon['name'],
                'description' => $addon['description'],
                'includes' => $addon['includes'],
                'monthlyPrice' => $addon['monthly_price'],
                'yearlyPrice' => $addon['yearly_price'],
                'isActive' => in_array($key, $activeAddons) || $isIncludedInPlan,
                'isIncludedInPlan' => $isIncludedInPlan,
                'canPurchase' => $canPurchase,
                'isFree' => $addon['free'] ?? false,
            ];
        }

        return $addons;
    }

    /**
     * Partner aktív addonjai
     */
    public function getActiveAddons(Partner $partner): array
    {
        return $partner->addons()
            ->where('status', 'active')
            ->get()
            ->map(fn (PartnerAddon $addon) => [
                'key' => $addon->addon_key,
                'name' => $addon->getName(),
                'activatedAt' => $addon->activated_at?->toIso8601String(),
                'includes' => $addon->getIncludedFeatures(),
            ])
            ->toArray();
    }

    /**
     * Addon aktiválása - Stripe Checkout Session létrehozása
     *
     * @throws ApiErrorException
     * @throws \Exception
     */
    public function subscribe(Partner $partner, string $addonKey): string
    {
        // Validáció
        $addonsConfig = config('plans.addons', []);
        if (! isset($addonsConfig[$addonKey])) {
            throw new \Exception('Ismeretlen addon: ' . $addonKey);
        }

        $addonConfig = $addonsConfig[$addonKey];
        $availableFor = $addonConfig['available_for'] ?? [];

        if (! in_array($partner->plan, $availableFor)) {
            throw new \Exception('Ez az addon nem elérhető a jelenlegi csomagodhoz.');
        }

        if ($partner->hasAddon($addonKey)) {
            throw new \Exception('Ez az addon már aktív.');
        }

        $isFree = $addonConfig['free'] ?? false;

        // Ingyenes addon: Stripe bypass
        if ($isFree) {
            PartnerAddon::updateOrCreate(
                ['partner_id' => $partner->id, 'addon_key' => $addonKey],
                [
                    'stripe_subscription_item_id' => null,
                    'status' => 'active',
                    'activated_at' => now(),
                    'canceled_at' => null,
                ]
            );

            Log::info('Partner free addon activated', [
                'partner_id' => $partner->id,
                'addon_key' => $addonKey,
            ]);

            return 'free';
        }

        // Fizetős addon: Stripe integráció
        if (! $partner->stripe_subscription_id) {
            throw new \Exception('Nincs aktív előfizetés.');
        }

        // Stripe price ID a billing cycle alapján
        $priceKey = $partner->billing_cycle === 'yearly' ? 'yearly' : 'monthly';
        $priceId = config("stripe.addons.{$addonKey}.{$priceKey}");

        if (! $priceId) {
            throw new \Exception("Stripe price nincs beállítva: {$addonKey}.{$priceKey}");
        }

        // Addon hozzáadása a meglévő subscription-höz (prorated)
        $subscriptionItem = SubscriptionItem::create([
            'subscription' => $partner->stripe_subscription_id,
            'price' => $priceId,
            'proration_behavior' => 'create_prorations',
        ]);

        // Addon rekord létrehozása/újraaktiválása
        PartnerAddon::updateOrCreate(
            ['partner_id' => $partner->id, 'addon_key' => $addonKey],
            [
                'stripe_subscription_item_id' => $subscriptionItem->id,
                'status' => 'active',
                'activated_at' => now(),
                'canceled_at' => null,
            ]
        );

        Log::info('Partner addon activated', [
            'partner_id' => $partner->id,
            'addon_key' => $addonKey,
            'subscription_item_id' => $subscriptionItem->id,
        ]);

        return $subscriptionItem->id;
    }

    /**
     * Addon lemondása
     *
     * @throws ApiErrorException
     * @throws \Exception
     */
    public function cancel(Partner $partner, string $addonKey): void
    {
        $addon = $partner->addons()
            ->where('addon_key', $addonKey)
            ->where('status', 'active')
            ->first();

        if (! $addon) {
            throw new \Exception('Ez az addon nincs aktív.');
        }

        // Stripe subscription item törlése
        if ($addon->stripe_subscription_item_id) {
            try {
                $subscriptionItem = SubscriptionItem::retrieve($addon->stripe_subscription_item_id);
                $subscriptionItem->delete([
                    'proration_behavior' => 'create_prorations',
                ]);
            } catch (ApiErrorException $e) {
                Log::warning('Failed to delete Stripe subscription item', [
                    'partner_id' => $partner->id,
                    'addon_key' => $addonKey,
                    'subscription_item_id' => $addon->stripe_subscription_item_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Addon rekord frissítése
        $addon->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        Log::info('Partner addon canceled', [
            'partner_id' => $partner->id,
            'addon_key' => $addonKey,
        ]);
    }

    /**
     * Webhook: Addon subscription item törölve a Stripe-on keresztül
     */
    public function handleSubscriptionItemDeleted(string $subscriptionItemId): void
    {
        $addon = PartnerAddon::where('stripe_subscription_item_id', $subscriptionItemId)
            ->where('status', 'active')
            ->first();

        if ($addon) {
            $addon->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            Log::info('Partner addon canceled via webhook', [
                'partner_id' => $addon->partner_id,
                'addon_key' => $addon->addon_key,
            ]);
        }
    }

    /**
     * Webhook: Teljes subscription törölve - minden addon is törlődik
     */
    public function handleSubscriptionCanceled(Partner $partner): void
    {
        $partner->addons()
            ->where('status', 'active')
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

        Log::info('All partner addons canceled (subscription canceled)', [
            'partner_id' => $partner->id,
        ]);
    }
}
