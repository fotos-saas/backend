<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StripeSetting;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    /**
     * Active Stripe settings from database
     */
    private ?StripeSetting $settings;

    /**
     * Initialize Stripe with API key from database
     */
    public function __construct()
    {
        $this->settings = StripeSetting::active();

        if ($this->settings && $this->settings->secret_key) {
            Stripe::setApiKey($this->settings->secret_key);
        } else {
            // Fallback to config/env if no active settings in database
            Stripe::setApiKey(config('stripe.secret_key'));
        }
    }

    /**
     * Create a Stripe Checkout Session for an order
     *
     * @return string Checkout URL
     */
    public function createCheckoutSession(Order $order): string
    {
        // Calculate discount ratio if coupon was applied
        $discountRatio = 0;
        if ($order->discount_huf > 0 && $order->subtotal_huf > 0) {
            $discountRatio = $order->discount_huf / $order->subtotal_huf;
        }

        $lineItems = $order->items->map(function ($item) use ($discountRatio) {
            // Calculate item's total (unit_price * quantity)
            $itemTotal = $item->unit_price_huf * $item->quantity;

            // Apply discount proportionally to this item
            $itemDiscount = round($itemTotal * $discountRatio);
            $discountedTotal = $itemTotal - $itemDiscount;

            // Calculate discounted unit price
            $discountedUnitPrice = round($discountedTotal / $item->quantity);

            return [
                'price_data' => [
                    'currency' => config('stripe.currency'),
                    'product_data' => [
                        'name' => $item->photo_id
                            ? "Photo #{$item->photo_id} - {$item->size}"
                            : "Print {$item->size}",
                    ],
                    // Stripe expects amount in smallest currency unit (fillér for HUF)
                    // Convert forint to fillér by multiplying by 100
                    'unit_amount' => $discountedUnitPrice * 100,
                ],
                'quantity' => $item->quantity,
            ];
        })->toArray();

        // Add coupon info as a 0 Ft line item (informational only)
        if ($order->discount_huf > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => config('stripe.currency'),
                    'product_data' => [
                        'name' => '✅ Kupon alkalmazva',
                        'description' => ($order->coupon?->code ?? 'Kedvezmény').' - Megtakarítás: '.
                            number_format($order->discount_huf, 0, ',', ' ').' Ft',
                    ],
                    'unit_amount' => 0, // 0 Ft = csak tájékoztatás
                ],
                'quantity' => 1,
            ];
        }

        // Add shipping cost as separate line item
        if ($order->shipping_cost_huf > 0) {
            $shippingName = '📦 Szállítás';
            if ($order->shippingMethod) {
                $shippingName .= ' - '.$order->shippingMethod->name;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => config('stripe.currency'),
                    'product_data' => [
                        'name' => $shippingName,
                    ],
                    'unit_amount' => $order->shipping_cost_huf * 100,
                ],
                'quantity' => 1,
            ];
        }

        // Add COD fee as separate line item
        if ($order->cod_fee_huf > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => config('stripe.currency'),
                    'product_data' => [
                        'name' => '💰 Utánvét díj',
                    ],
                    'unit_amount' => $order->cod_fee_huf * 100,
                ],
                'quantity' => 1,
            ];
        }

        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => config('stripe.success_url').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('stripe.cancel_url'),
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->getCustomerEmail(),
            'metadata' => [
                'order_id' => $order->id,
                'coupon_code' => $order->coupon?->code ?? null,
                'discount_huf' => $order->discount_huf,
            ],
        ];

        $session = Session::create($sessionParams);

        Log::info('Stripe Checkout Session created', [
            'order_id' => $order->id,
            'session_id' => $session->id,
            'url' => $session->url,
        ]);

        return $session->url;
    }

    /**
     * Construct a webhook event from payload and signature
     *
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        $webhookSecret = $this->settings?->webhook_secret ?? config('stripe.webhook_secret');

        return Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );
    }

    /**
     * Handle successful payment webhook
     */
    public function handleSuccessfulPayment(string $sessionId): void
    {
        try {
            $session = Session::retrieve($sessionId);

            $orderId = $session->metadata->order_id ?? $session->client_reference_id;

            if (! $orderId) {
                Log::error('Order ID not found in Stripe session', ['session_id' => $sessionId]);

                return;
            }

            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Order not found for Stripe payment', ['order_id' => $orderId]);

                return;
            }

            // Update order status and payment intent
            $order->update([
                'status' => 'paid',
                'stripe_pi' => $session->payment_intent,
            ]);

            Log::info('Order marked as paid', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'payment_intent' => $session->payment_intent,
            ]);

            // Automatically issue invoice after successful payment
            if ($order->status === 'paid' && ! $order->invoice_no) {
                try {
                    app(InvoicingService::class)->issueInvoiceForOrder($order);
                } catch (\Exception $e) {
                    Log::error('Auto invoice failed after payment', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error handling successful payment', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
