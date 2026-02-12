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
                'customer.subscription.created' => $this->handleSubscriptionCreated($event),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                'invoice.paid' => $this->handleInvoicePaid($event),
                default => Log::info('Unhandled Stripe event type', ['type' => $event->type]),
            };

            return response()->json(['status' => 'success']);
        } catch (SignatureVerificationException $e) {
            report($e);

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            report($e);

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

    /**
     * Handle customer.subscription.created event
     *
     * @param  \Stripe\Event  $event
     */
    private function handleSubscriptionCreated($event): void
    {
        $subscription = $event->data->object;

        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
            'status' => $subscription->status,
        ]);
    }

    /**
     * Handle customer.subscription.updated event
     *
     * @param  \Stripe\Event  $event
     */
    private function handleSubscriptionUpdated($event): void
    {
        $subscription = $event->data->object;

        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        // Update partner subscription status if exists
        $partner = \App\Models\Partner::where('stripe_subscription_id', $subscription->id)->first();

        if ($partner) {
            $partner->update([
                'subscription_status' => $subscription->status,
                'subscription_ends_at' => $subscription->current_period_end
                    ? \Carbon\Carbon::createFromTimestamp($subscription->current_period_end)
                    : null,
            ]);
        }
    }

    /**
     * Handle customer.subscription.deleted event
     *
     * @param  \Stripe\Event  $event
     */
    private function handleSubscriptionDeleted($event): void
    {
        $subscription = $event->data->object;

        Log::info('Subscription deleted', [
            'subscription_id' => $subscription->id,
        ]);

        // Update partner subscription status if exists
        $partner = \App\Models\Partner::where('stripe_subscription_id', $subscription->id)->first();

        if ($partner) {
            $partner->update([
                'subscription_status' => 'canceled',
            ]);
        }
    }

    /**
     * Handle invoice.paid event
     *
     * @param  \Stripe\Event  $event
     */
    private function handleInvoicePaid($event): void
    {
        $invoice = $event->data->object;

        Log::info('Invoice paid', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer,
            'amount_paid' => $invoice->amount_paid,
        ]);

        // Registration checkout handling is done via checkout.session.completed event
        // Invoice events are logged but no action needed
    }
}
