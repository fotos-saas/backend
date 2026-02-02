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

        foreach (Partner::ADDONS as $key => $addon) {
            // Addon csak Alap csomaghoz vásárolható
            $canPurchase = $partner->plan === 'alap' && ! in_array($key, $activeAddons);

            // Iskola/Stúdió esetén automatikusan "aktív" (benne van a csomagban)
            $isIncludedInPlan = in_array($partner->plan, ['iskola', 'studio']);

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
        if (! isset(Partner::ADDONS[$addonKey])) {
            throw new \Exception('Ismeretlen addon: ' . $addonKey);
        }

        if ($partner->plan !== 'alap') {
            throw new \Exception('Addon csak Alap csomaghoz vásárolható.');
        }

        if ($partner->hasAddon($addonKey)) {
            throw new \Exception('Ez az addon már aktív.');
        }

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

        // Addon rekord létrehozása
        $addon = PartnerAddon::create([
            'partner_id' => $partner->id,
            'addon_key' => $addonKey,
            'stripe_subscription_item_id' => $subscriptionItem->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);

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
