<?php

namespace App\Services\SuperAdmin;

use App\Models\Partner;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Stripe\Subscription;

/**
 * Super Admin Stripe Service
 *
 * Kezeli a super admin által végrehajtott Stripe műveleteket:
 * - Subscriber terhelése (charge)
 * - Csomag váltás
 * - Előfizetés lemondása
 */
class SuperAdminStripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Stripe Customer ID formátum validáció
     */
    public function isValidCustomerId(?string $customerId): bool
    {
        if (empty($customerId)) {
            return false;
        }

        return (bool) preg_match('/^cus_[a-zA-Z0-9]+$/', $customerId);
    }

    /**
     * Partner terhelése új számlával
     *
     * @return array ['success' => bool, 'invoiceId' => ?string, 'error' => ?string]
     */
    public function chargePartner(Partner $partner, int $amount, string $description): array
    {
        if (! $this->isValidCustomerId($partner->stripe_customer_id)) {
            Log::warning('Invalid Stripe customer ID format', [
                'partner_id' => $partner->id,
                'stripe_customer_id' => $partner->stripe_customer_id,
            ]);

            return [
                'success' => false,
                'invoiceId' => null,
                'error' => 'Érvénytelen Stripe customer ID formátum.',
            ];
        }

        try {
            // Create invoice item
            InvoiceItem::create([
                'customer' => $partner->stripe_customer_id,
                'amount' => $amount,
                'currency' => config('stripe.currency', 'huf'),
                'description' => $description,
            ]);

            // Create and finalize invoice
            $invoice = Invoice::create([
                'customer' => $partner->stripe_customer_id,
                'auto_advance' => true,
                'collection_method' => 'charge_automatically',
            ]);

            $invoice->finalizeInvoice();
            $invoice->pay();

            return [
                'success' => true,
                'invoiceId' => $invoice->id,
                'error' => null,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe charge error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'invoiceId' => null,
                'error' => 'Stripe hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Csomag váltás Stripe-ban
     *
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function changePlan(Partner $partner, string $newPriceId): array
    {
        if (! $partner->stripe_subscription_id) {
            return ['success' => true, 'error' => null];
        }

        try {
            $subscription = Subscription::retrieve($partner->stripe_subscription_id);

            Subscription::update($partner->stripe_subscription_id, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);

            return ['success' => true, 'error' => null];
        } catch (ApiErrorException $e) {
            Log::error('Stripe plan change error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Stripe hiba: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Előfizetés lemondása
     *
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function cancelSubscription(Partner $partner, bool $immediate): array
    {
        if (! $partner->stripe_subscription_id) {
            return ['success' => true, 'error' => null];
        }

        try {
            if ($immediate) {
                Subscription::update($partner->stripe_subscription_id, [
                    'cancel_at_period_end' => false,
                ]);
                $subscription = Subscription::retrieve($partner->stripe_subscription_id);
                $subscription->cancel();
            } else {
                Subscription::update($partner->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            }

            return ['success' => true, 'error' => null];
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Stripe hiba: ' . $e->getMessage(),
            ];
        }
    }
}
