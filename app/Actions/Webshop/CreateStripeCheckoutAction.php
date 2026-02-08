<?php

declare(strict_types=1);

namespace App\Actions\Webshop;

use App\Models\ShopOrder;
use App\Models\TabloPartner;
use App\Services\PartnerStripeService;
use Illuminate\Support\Facades\Log;

class CreateStripeCheckoutAction
{
    public function __construct(
        private readonly PartnerStripeService $stripeService,
    ) {}

    public function execute(ShopOrder $order, string $shopToken): array
    {
        $partner = TabloPartner::findOrFail($order->tablo_partner_id);
        $stripeService = $this->stripeService;

        try {
            $client = $stripeService->getStripeClient($partner);

            // Line items létrehozása a rendelés tételeiből
            $lineItems = [];
            foreach ($order->items()->with('product.paperSize', 'product.paperType')->get() as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'huf',
                        'product_data' => [
                            'name' => "{$item->paper_size_name} - {$item->paper_type_name}",
                            'description' => $item->media?->file_name ?? 'Fotónyomtatás',
                        ],
                        'unit_amount' => $item->unit_price_huf * 100,
                    ],
                    'quantity' => $item->quantity,
                ];
            }

            // Szállítás mint külön tétel
            if ($order->shipping_cost_huf > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'huf',
                        'product_data' => [
                            'name' => 'Szállítási költség',
                        ],
                        'unit_amount' => $order->shipping_cost_huf * 100,
                    ],
                    'quantity' => 1,
                ];
            }

            $baseUrl = config('app.frontend_url', 'https://tablostudio.hu');
            $successUrl = "{$baseUrl}/shop/{$shopToken}/success?order={$order->order_number}";
            $cancelUrl = "{$baseUrl}/shop/{$shopToken}?payment=cancelled";

            $session = $client->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $order->id,
                'customer_email' => $order->customer_email,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'partner_id' => $partner->id,
                    'type' => 'webshop',
                ],
            ]);

            $order->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            Log::info('Webshop Stripe Checkout Session created', [
                'partner_id' => $partner->id,
                'order_id' => $order->id,
                'session_id' => $session->id,
            ]);

            return [
                'success' => true,
                'checkout_url' => $session->url,
            ];
        } catch (\Exception $e) {
            Log::error('Webshop Stripe Checkout creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Fizetés indítása sikertelen. Kérjük próbálja újra.',
            ];
        }
    }
}
