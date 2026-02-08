<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GuestBillingCharge;
use App\Models\TabloPartner;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class PartnerStripeService
{
    /**
     * Stripe kliens létrehozása partner saját kulcsával.
     * NEM globális setApiKey — per-request, multi-tenant safe.
     */
    public function getStripeClient(TabloPartner $partner): StripeClient
    {
        $secretKey = $partner->getDecryptedStripeSecretKey();

        if (! $secretKey) {
            throw new \RuntimeException('Partner Stripe secret key nincs beállítva.');
        }

        return new StripeClient($secretKey);
    }

    /**
     * Stripe Checkout Session létrehozása egy terheléshez.
     *
     * @return array{checkout_url: string, session_id: string}
     */
    public function createCheckoutSession(
        GuestBillingCharge $charge,
        TabloPartner $partner,
        string $successUrl,
        string $cancelUrl
    ): array {
        $client = $this->getStripeClient($partner);

        $session = $client->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'huf',
                        'product_data' => [
                            'name' => $charge->service_label,
                            'description' => $charge->description ?? $charge->charge_number,
                        ],
                        'unit_amount' => $charge->amount_huf * 100,
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $successUrl . '?payment=success&charge_id=' . $charge->id,
            'cancel_url' => $cancelUrl . '?payment=cancelled',
            'client_reference_id' => (string) $charge->id,
            'metadata' => [
                'charge_id' => $charge->id,
                'charge_number' => $charge->charge_number,
                'partner_id' => $partner->id,
                'project_id' => $charge->tablo_project_id,
            ],
        ]);

        $charge->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        Log::info('Partner Stripe Checkout Session created', [
            'partner_id' => $partner->id,
            'charge_id' => $charge->id,
            'session_id' => $session->id,
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /**
     * Webhook event rekonstruálása és validálása.
     *
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signature, TabloPartner $partner): Event
    {
        $webhookSecret = $partner->getDecryptedStripeWebhookSecret();

        if (! $webhookSecret) {
            throw new \RuntimeException('Partner Stripe webhook secret nincs beállítva.');
        }

        return Webhook::constructEvent($payload, $signature, $webhookSecret);
    }

    /**
     * Stripe kulcsok validálása — teszteli a balance lekérdezéssel.
     */
    public function validateKeys(TabloPartner $partner): bool
    {
        try {
            $client = $this->getStripeClient($partner);
            $client->balance->retrieve();

            return true;
        } catch (\Exception $e) {
            Log::warning('Partner Stripe key validation failed', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
