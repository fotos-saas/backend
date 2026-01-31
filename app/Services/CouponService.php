<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Package;
use App\Models\WorkSession;

class CouponService
{
    /**
     * Validate coupon for given context with priority logic
     *
     * Priority: WorkSession > Package > None
     *
     * @param  Coupon  $coupon  Coupon to validate
     * @param  Package|null  $package  Package context
     * @param  WorkSession|null  $workSession  WorkSession context (has priority)
     * @return bool Is coupon valid in this context
     */
    public function validateCouponForContext(
        Coupon $coupon,
        ?Package $package = null,
        ?WorkSession $workSession = null
    ): bool {
        // Check basic coupon validity first
        if (! $coupon->enabled) {
            return false;
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return false;
        }

        if ($coupon->max_usage && $coupon->usage_count >= $coupon->max_usage) {
            return false;
        }

        // WorkSession has highest priority
        if ($workSession) {
            return $coupon->isValidForWorkSession($workSession);
        }

        // Package is second priority
        if ($package) {
            return $coupon->isValidForPackage($package);
        }

        // No context restrictions
        return true;
    }

    /**
     * Get available coupons for context
     *
     * @param  Package|null  $package  Package context
     * @param  WorkSession|null  $workSession  WorkSession context
     * @return \Illuminate\Database\Eloquent\Collection<Coupon>
     */
    public function getAvailableCouponsForContext(
        ?Package $package = null,
        ?WorkSession $workSession = null
    ) {
        $query = Coupon::valid();

        // If WorkSession context exists and has specific policy
        if ($workSession && $workSession->coupon_policy === 'specific') {
            return $query->whereIn('id', $workSession->allowed_coupon_ids ?? [])->get();
        }

        // If WorkSession says none
        if ($workSession && $workSession->coupon_policy === 'none') {
            return collect([]);
        }

        // If Package context exists and has specific policy (and no WorkSession override)
        if ($package && $package->coupon_policy === 'specific') {
            return $query->whereIn('id', $package->allowed_coupon_ids ?? [])->get();
        }

        // If Package says none (and no WorkSession override)
        if ($package && $package->coupon_policy === 'none') {
            return collect([]);
        }

        // All valid coupons are available
        return $query->get();
    }
}
