<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * StripeService instance
     */
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Handle Stripe webhook events
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            Log::warning('Stripe webhook received without signature');

            return response()->json(['error' => 'No signature'], 400);
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);

            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);

            // Handle different event types
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                default => Log::info('Unhandled Stripe event type', ['type' => $event->type]),
            };

            return response()->json(['status' => 'success']);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle checkout.session.completed event
     *
     * @param  \Stripe\Event  $event
     */
    private function handleCheckoutSessionCompleted($event): void
    {
        $session = $event->data->object;

        Log::info('Checkout session completed', [
            'session_id' => $session->id,
            'payment_status' => $session->payment_status,
        ]);

        if ($session->payment_status === 'paid') {
            $this->stripeService->handleSuccessfulPayment($session->id);
        }
    }

    /**
     * Handle payment_intent.succeeded event
     *
     * @param  \Stripe\Event  $event
     */
    private function handlePaymentIntentSucceeded($event): void
    {
        $paymentIntent = $event->data->object;

        Log::info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
        ]);
    }

    /**
     * Handle payment_intent.payment_failed event
     *
     * @param  \Stripe\Event  $event
     */
    private function handlePaymentIntentFailed($event): void
    {
        $paymentIntent = $event->data->object;

        Log::warning('Payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'failure_message' => $paymentIntent->last_payment_error?->message,
        ]);
    }
}
