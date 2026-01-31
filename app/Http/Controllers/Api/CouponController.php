<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\WorkSession;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private CouponService $couponService
    ) {}

    /**
     * Validate coupon by code with optional context
     */
    public function show(Request $request, string $code)
    {
        // Reject empty or whitespace-only codes
        if (empty(trim($code))) {
            return response()->json([
                'message' => 'Érvénytelen kupon kód',
            ], 404);
        }

        $coupon = Coupon::where('code', $code)
            ->valid()
            ->first();

        if (! $coupon) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt kupon kód',
            ], 404);
        }

        // Get optional parameters
        $orderTotal = $request->input('order_total', 0);
        $albumId = $request->input('album_id');
        $package = null;
        $workSession = null;

        if ($request->has('package_id')) {
            $package = Package::find($request->input('package_id'));

            // Override order total with package price (package has fixed price)
            if ($package && $package->price) {
                $orderTotal = $package->price;
            }
        }

        if ($request->has('work_session_id')) {
            $workSession = WorkSession::find($request->input('work_session_id'));
        }

        // Validate coupon in context (package/work session)
        $isValidInContext = $this->couponService->validateCouponForContext(
            $coupon,
            $package,
            $workSession
        );

        if (! $isValidInContext) {
            // Determine specific context error message
            $errorMessage = 'Ez a kupon nem alkalmazható a kiválasztott beállításokkal';

            if ($workSession) {
                $errorMessage = 'Ez a kupon nem használható ebben a munkamenetben';
            } elseif ($package) {
                $errorMessage = 'Ez a kupon nem használható ehhez a csomaghoz';
            }

            return response()->json([
                'message' => $errorMessage,
            ], 403);
        }

        // Get current user (if authenticated)
        $user = $request->user();

        // Determine if we are in package mode
        $isPackageMode = $request->has('package_id') && $package !== null;

        // Validate coupon for order (min_order_value, allowed_emails, allowed_albums)
        if (! $coupon->isValidForOrder($orderTotal, $user, $albumId, $isPackageMode)) {
            // Determine specific error message
            // Skip min_order_value error if in package mode
            if (! $isPackageMode && $coupon->min_order_value && $orderTotal < $coupon->min_order_value) {
                return response()->json([
                    'message' => 'Minimum rendelési érték nem teljesül',
                    'code' => 'min_order_value_not_met',
                    'requiredValue' => $coupon->min_order_value,
                ], 422);
            }

            if ($user && $coupon->allowed_emails && count($coupon->allowed_emails) > 0) {
                if (! in_array($user->email, $coupon->allowed_emails)) {
                    return response()->json([
                        'message' => 'Kupon nem érvényes erre a felhasználóra',
                        'code' => 'email_not_allowed',
                    ], 403);
                }
            }

            if ($albumId && $coupon->allowed_album_ids && count($coupon->allowed_album_ids) > 0) {
                if (! in_array($albumId, $coupon->allowed_album_ids)) {
                    return response()->json([
                        'message' => 'Kupon nem érvényes erre az albumra',
                        'code' => 'album_not_allowed',
                    ], 403);
                }
            }

            // Generic error
            return response()->json([
                'message' => 'Kupon nem használható',
            ], 403);
        }

        return response()->json([
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'enabled' => $coupon->enabled,
            'expiresAt' => $coupon->expires_at?->toISOString(),
            'minOrderValue' => $coupon->min_order_value,
            'allowedEmails' => $coupon->allowed_emails,
            'allowedAlbumIds' => $coupon->allowed_album_ids,
            'allowedSizes' => $coupon->allowed_sizes,
            'maxUsage' => $coupon->max_usage,
            'usageCount' => $coupon->usage_count,
            'firstOrderOnly' => $coupon->first_order_only,
            'autoApply' => $coupon->auto_apply,
            'stackable' => $coupon->stackable,
            'description' => $coupon->description,
        ]);
    }

    /**
     * Get available coupons for context
     */
    public function index(Request $request)
    {
        $package = null;
        $workSession = null;

        if ($request->has('package_id')) {
            $package = Package::find($request->input('package_id'));
        }

        if ($request->has('work_session_id')) {
            $workSession = WorkSession::find($request->input('work_session_id'));
        }

        $coupons = $this->couponService->getAvailableCouponsForContext($package, $workSession);

        return response()->json(
            $coupons->map(fn ($coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'enabled' => $coupon->enabled,
                'expiresAt' => $coupon->expires_at?->toISOString(),
                'minOrderValue' => $coupon->min_order_value,
                'allowedEmails' => $coupon->allowed_emails,
                'allowedAlbumIds' => $coupon->allowed_album_ids,
                'allowedSizes' => $coupon->allowed_sizes,
                'maxUsage' => $coupon->max_usage,
                'usageCount' => $coupon->usage_count,
                'firstOrderOnly' => $coupon->first_order_only,
                'autoApply' => $coupon->auto_apply,
                'stackable' => $coupon->stackable,
                'description' => $coupon->description,
            ])
        );
    }
}
