<?php

namespace App\Actions\Order;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VerifyOrderPaymentAction
{
    public function execute(Order $order, string $sessionId): JsonResponse
    {
        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            $orderIdFromSession = $session->metadata->order_id ?? $session->client_reference_id;

            if ((int) $orderIdFromSession !== $order->id) {
                return response()->json([
                    'message' => 'Session does not match order',
                ], 400);
            }

            if ($session->payment_status === 'paid' && $order->status === 'payment_pending') {
                $order->update([
                    'status' => 'paid',
                    'stripe_pi' => $session->payment_intent,
                ]);

                Log::info('Order payment verified', [
                    'order_id' => $order->id,
                    'session_id' => $sessionId,
                ]);
            }

            return response()->json([
                'order' => $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession']),
                'payment_status' => $session->payment_status,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
