<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\HandlePartnerPaymentSuccessAction;
use App\Http\Controllers\Controller;
use App\Models\GuestBillingCharge;
use App\Models\TabloPartner;
use App\Services\PartnerStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class PartnerStripeWebhookController extends Controller
{
    public function __construct(
        private readonly PartnerStripeService $stripeService,
        private readonly HandlePartnerPaymentSuccessAction $paymentSuccessAction,
    ) {}

    /**
     * POST /api/partner-stripe/webhook/{partnerId}
     */
    public function handle(Request $request, int $partnerId): JsonResponse
    {
        $partner = TabloPartner::find($partnerId);

        if (! $partner || ! $partner->hasStripePaymentEnabled()) {
            Log::warning('Partner Stripe webhook: invalid partner', ['partner_id' => $partnerId]);
            return response()->json(['error' => 'Invalid partner'], 404);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature, $partner);
        } catch (SignatureVerificationException $e) {
            Log::warning('Partner Stripe webhook signature mismatch', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Partner Stripe webhook error', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Webhook error'], 400);
        }

        Log::info('Partner Stripe webhook received', [
            'partner_id' => $partnerId,
            'event_type' => $event->type,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutComplete($event),
            'charge.refunded' => $this->handleRefund($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleCheckoutComplete(\Stripe\Event $event): void
    {
        $session = $event->data->object;
        $chargeId = $session->metadata->charge_id ?? $session->client_reference_id;

        if (! $chargeId) {
            Log::error('Partner webhook: charge_id not found in session', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $charge = GuestBillingCharge::find($chargeId);

        if (! $charge) {
            Log::error('Partner webhook: charge not found', ['charge_id' => $chargeId]);
            return;
        }

        $this->paymentSuccessAction->execute($charge, $session->payment_intent ?? '');
    }

    private function handleRefund(\Stripe\Event $event): void
    {
        $refundObject = $event->data->object;
        $paymentIntentId = $refundObject->payment_intent ?? null;

        if (! $paymentIntentId) {
            return;
        }

        $charge = GuestBillingCharge::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $charge) {
            Log::warning('Partner webhook refund: charge not found for PI', [
                'payment_intent' => $paymentIntentId,
            ]);
            return;
        }

        $charge->update(['status' => GuestBillingCharge::STATUS_REFUNDED]);

        Log::info('Partner charge refunded via webhook', [
            'charge_id' => $charge->id,
            'payment_intent' => $paymentIntentId,
        ]);
    }
}
