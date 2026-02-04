<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Two-Factor Authentication Controller
 *
 * Currently a placeholder - 2FA is not yet implemented.
 * This controller provides stub endpoints that return "not available" responses.
 */
class TwoFactorController extends Controller
{
    /**
     * Enable 2FA (not yet implemented)
     */
    public function enable2FA(Request $request)
    {
        if (! Setting::get('auth.two_factor_available', false)) {
            return response()->json([
                'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
                'available' => false,
            ], 503);
        }

        // TODO: Implement 2FA
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés hamarosan elérhető lesz.',
            'available' => false,
        ], 503);
    }

    /**
     * Confirm 2FA setup (not yet implemented)
     */
    public function confirm2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }

    /**
     * Disable 2FA (not yet implemented)
     */
    public function disable2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }

    /**
     * Verify 2FA code (not yet implemented)
     */
    public function verify2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }
}
