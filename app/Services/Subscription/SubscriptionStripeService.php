<?php

namespace App\Services\Subscription;

use App\Models\Partner;
use Illuminate\Support\Facades\Log;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\Subscription;

/**
 * Subscription Stripe Service
 *
 * Kezeli az előfizetésekkel kapcsolatos Stripe műveleteket:
 * - Checkout session létrehozás
 * - Subscription kezelés (cancel, resume, pause, unpause)
 * - Portal session
 * - Számlák lekérése
 */
class SubscriptionStripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Checkout Session létrehozása regisztrációhoz
     */
    public function createCheckoutSession(array $data, string $registrationToken): Session
    {
        $priceId = config("stripe.prices.{$data['plan']}.{$data['billing_cycle']}");

        if (empty($priceId)) {
            throw new \InvalidArgumentException('A kiválasztott csomag jelenleg nem elérhető.');
        }

        return Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => config('stripe.success_url').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('stripe.cancel_url'),
            'customer_email' => $data['email'],
            'metadata' => [
                'registration_token' => $registrationToken,
                'plan' => $data['plan'],
                'billing_cycle' => $data['billing_cycle'],
            ],
            'subscription_data' => [
                'trial_period_days' => 14,
                'metadata' => [
                    'registration_token' => $registrationToken,
                    'plan' => $data['plan'],
                    'billing_cycle' => $data['billing_cycle'],
                ],
            ],
            'locale' => 'hu',
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'auto',
            'tax_id_collection' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Checkout Session lekérése
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return Session::retrieve([
            'id' => $sessionId,
            'expand' => ['subscription', 'customer'],
        ]);
    }

    /**
     * Előfizetés részleteinek lekérése (cache-elve)
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        $cacheKey = "stripe_subscription:{$subscriptionId}";

        return cache()->remember($cacheKey, now()->addMinutes(5), function () use ($subscriptionId) {
            $subscription = Subscription::retrieve([
                'id' => $subscriptionId,
                'expand' => ['items.data.price'],
            ]);

            // Számoljuk ki a teljes havi költséget
            $totalMonthlyAmount = 0;
            foreach ($subscription->items->data as $item) {
                $price = $item->price;
                $amount = $price->unit_amount * $item->quantity;

                // Ha éves, oszd 12-vel a havi egyenértékhez
                if ($price->recurring && $price->recurring->interval === 'year') {
                    $amount = (int) round($amount / 12);
                }

                $totalMonthlyAmount += $amount;
            }

            return [
                'stripe_status' => $subscription->status,
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'monthly_cost' => (int) round($totalMonthlyAmount / 100),
                'currency' => $subscription->currency,
            ];
        });
    }

    /**
     * Stripe subscription cache törlése
     */
    public function clearSubscriptionCache(string $subscriptionId): void
    {
        cache()->forget("stripe_subscription:{$subscriptionId}");
    }

    /**
     * Customer Portal session létrehozása
     */
    public function createPortalSession(string $customerId, string $returnUrl): PortalSession
    {
        return PortalSession::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Előfizetés lemondása (időszak végén)
     */
    public function cancelAtPeriodEnd(string $subscriptionId): Subscription
    {
        $subscription = Subscription::update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);

        $this->clearSubscriptionCache($subscriptionId);

        return $subscription;
    }

    /**
     * Előfizetés újraaktiválása (lemondás visszavonása)
     */
    public function resumeSubscription(string $subscriptionId): Subscription
    {
        $subscription = Subscription::update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);

        $this->clearSubscriptionCache($subscriptionId);

        return $subscription;
    }

    /**
     * Előfizetés szüneteltetése (alacsonyabb árra váltás)
     */
    public function pauseSubscription(Partner $partner): Subscription
    {
        $pausedPriceId = config("stripe.prices.{$partner->plan}.paused");

        if (empty($pausedPriceId)) {
            throw new \InvalidArgumentException('A szüneteltetés jelenleg nem elérhető ehhez a csomaghoz.');
        }

        $subscription = Subscription::retrieve($partner->stripe_subscription_id);
        $subscriptionItemId = $subscription->items->data[0]->id;

        $updatedSubscription = Subscription::update($partner->stripe_subscription_id, [
            'items' => [[
                'id' => $subscriptionItemId,
                'price' => $pausedPriceId,
            ]],
            'proration_behavior' => 'create_prorations',
        ]);

        $this->clearSubscriptionCache($partner->stripe_subscription_id);

        return $updatedSubscription;
    }

    /**
     * Előfizetés újraindítása (eredeti árra visszaváltás)
     */
    public function unpauseSubscription(Partner $partner): Subscription
    {
        $originalPriceId = config("stripe.prices.{$partner->plan}.{$partner->billing_cycle}");

        if (empty($originalPriceId)) {
            throw new \InvalidArgumentException('Hiba történt az eredeti ár visszaállításakor.');
        }

        $subscription = Subscription::retrieve($partner->stripe_subscription_id);
        $subscriptionItemId = $subscription->items->data[0]->id;

        $updatedSubscription = Subscription::update($partner->stripe_subscription_id, [
            'items' => [[
                'id' => $subscriptionItemId,
                'price' => $originalPriceId,
            ]],
            'proration_behavior' => 'create_prorations',
        ]);

        $this->clearSubscriptionCache($partner->stripe_subscription_id);

        return $updatedSubscription;
    }

    /**
     * Számlák lekérése Stripe-ból
     */
    public function getInvoices(string $customerId, array $params = []): \Stripe\Collection
    {
        $queryParams = array_merge([
            'customer' => $customerId,
            'limit' => 20,
        ], $params);

        return Invoice::all($queryParams);
    }

    /**
     * Session státusz ellenőrzése
     */
    public function verifySession(string $sessionId): array
    {
        $session = Session::retrieve($sessionId);

        return [
            'status' => $session->status,
            'payment_status' => $session->payment_status,
        ];
    }
}
