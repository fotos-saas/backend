<?php

namespace App\Services\Stripe;

use Illuminate\Support\Facades\Log;
use Stripe\Coupon;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Subscription;

final class StripeSubscriptionService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Create a Stripe coupon for a partner discount.
     *
     * @throws ApiErrorException
     */
    public function createCoupon(int $percent, ?int $durationMonths, int $partnerId): Coupon
    {
        $idempotencyKey = "coupon-{$partnerId}-" . now()->format('Y-m-d-H-i');

        return Coupon::create([
            'percent_off' => $percent,
            'duration' => $durationMonths ? 'repeating' : 'forever',
            'duration_in_months' => $durationMonths,
            'max_redemptions' => 1,
            'name' => "Partner #{$partnerId} - {$percent}%",
            'metadata' => ['partner_id' => (string) $partnerId],
        ], ['idempotency_key' => $idempotencyKey]);
    }

    /**
     * Apply a coupon to a subscription.
     * Uses proration_behavior: none to apply from next billing cycle.
     *
     * @throws ApiErrorException
     */
    public function applyDiscount(string $subscriptionId, string $couponId): Subscription
    {
        return Subscription::update($subscriptionId, [
            'coupon' => $couponId,
            'proration_behavior' => 'none', // Apply from next cycle
        ]);
    }

    /**
     * Remove discount from a subscription.
     *
     * @throws ApiErrorException
     */
    public function removeDiscount(string $subscriptionId): Subscription
    {
        return Subscription::update($subscriptionId, [
            'coupon' => '',
        ]);
    }

    /**
     * Delete a coupon from Stripe.
     * Silently fails if coupon doesn't exist or is already deleted.
     */
    public function deleteCoupon(string $couponId): void
    {
        try {
            $coupon = Coupon::retrieve($couponId);
            $coupon->delete();
        } catch (ApiErrorException $e) {
            Log::warning('Failed to delete Stripe coupon', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
