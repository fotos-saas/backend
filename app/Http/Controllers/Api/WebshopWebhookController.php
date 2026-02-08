<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\TabloPartner;
use App\Services\PartnerStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class WebshopWebhookController extends Controller
{
    public function __construct(
        private readonly PartnerStripeService $stripeService,
    ) {}

    public function handle(Request $request, int $partnerId): JsonResponse
    {
        $partner = TabloPartner::find($partnerId);

        if (!$partner || !$partner->hasStripePaymentEnabled()) {
            Log::warning('Webshop webhook: invalid partner', ['partner_id' => $partnerId]);
            return response()->json(['error' => 'Invalid partner'], 404);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature, $partner);
        } catch (SignatureVerificationException $e) {
            Log::warning('Webshop webhook signature mismatch', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Webshop webhook error', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Webhook error'], 400);
        }

        Log::info('Webshop webhook received', [
            'partner_id' => $partnerId,
            'event_type' => $event->type,
        ]);

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutComplete($event, $partnerId);
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutComplete(\Stripe\Event $event, int $partnerId): void
    {
        $session = $event->data->object;

        // Webshop típusú-e
        $type = $session->metadata->type ?? null;
        if ($type !== 'webshop') {
            return;
        }

        $orderId = $session->metadata->order_id ?? $session->client_reference_id;

        if (!$orderId) {
            Log::error('Webshop webhook: order_id not found', ['session_id' => $session->id]);
            return;
        }

        $order = ShopOrder::where('tablo_partner_id', $partnerId)->find($orderId);

        if (!$order) {
            Log::error('Webshop webhook: order not found or partner mismatch', ['order_id' => $orderId, 'partner_id' => $partnerId]);
            return;
        }

        if ($order->status !== ShopOrder::STATUS_PENDING) {
            Log::info('Webshop webhook: order already processed', ['order_id' => $orderId]);
            return;
        }

        $order->update([
            'status' => ShopOrder::STATUS_PAID,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'paid_at' => now(),
        ]);

        Log::info('Webshop order paid', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_huf' => $order->total_huf,
        ]);
    }
}
