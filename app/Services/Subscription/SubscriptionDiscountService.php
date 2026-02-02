<?php

namespace App\Services\Subscription;

use App\DTOs\CreateDiscountData;
use App\Models\Partner;
use App\Models\SubscriptionDiscount;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Support\Facades\DB;

final class SubscriptionDiscountService
{
    public function __construct(
        private readonly StripeSubscriptionService $stripeService,
    ) {}

    /**
     * Apply a discount to a partner's subscription.
     * Removes any existing discount first.
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function apply(CreateDiscountData $data): SubscriptionDiscount
    {
        return DB::transaction(function () use ($data) {
            $partner = Partner::findOrFail($data->partnerId);

            // Remove existing discount if any
            $this->removeExisting($partner);

            // Create Stripe coupon
            $coupon = $this->stripeService->createCoupon(
                $data->percent,
                $data->durationMonths,
                $data->partnerId,
            );

            // Apply to Stripe subscription if exists
            if ($partner->stripe_subscription_id) {
                $this->stripeService->applyDiscount(
                    $partner->stripe_subscription_id,
                    $coupon->id,
                );
            }

            // Create DB record
            return SubscriptionDiscount::create([
                'partner_id' => $data->partnerId,
                'percent' => $data->percent,
                'valid_until' => $data->durationMonths
                    ? now()->addMonths($data->durationMonths)
                    : null,
                'note' => $data->note,
                'stripe_coupon_id' => $coupon->id,
                'created_by' => $data->createdBy,
            ]);
        });
    }

    /**
     * Remove the active discount from a partner.
     */
    public function remove(Partner $partner): void
    {
        DB::transaction(function () use ($partner) {
            $discount = $partner->activeDiscount;

            if (! $discount) {
                return;
            }

            // Remove from Stripe subscription
            if ($partner->stripe_subscription_id) {
                $this->stripeService->removeDiscount($partner->stripe_subscription_id);
            }

            // Delete Stripe coupon
            if ($discount->stripe_coupon_id) {
                $this->stripeService->deleteCoupon($discount->stripe_coupon_id);
            }

            // Soft delete the discount record
            $discount->delete();
        });
    }

    /**
     * Remove existing discount if any.
     */
    private function removeExisting(Partner $partner): void
    {
        if ($partner->activeDiscount) {
            $this->remove($partner);
            // Refresh relationship
            $partner->unsetRelation('activeDiscount');
        }
    }
}
