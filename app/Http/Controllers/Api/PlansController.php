<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Plans Controller
 *
 * Provides plan configuration to frontend.
 * Single source of truth: config/plans.php
 *
 * @see config/plans.php
 */
class PlansController extends Controller
{
    /**
     * Get all plan configurations
     *
     * Returns plans, addons, and storage addon pricing.
     * Public endpoint - no authentication required.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => config('plans.plans'),
            'addons' => config('plans.addons'),
            'storage_addon' => config('plans.storage_addon'),
        ]);
    }
}
