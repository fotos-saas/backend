<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    /**
     * Get all active payment methods
     */
    public function index(): JsonResponse
    {
        $paymentMethods = PaymentMethod::active()
            ->ordered()
            ->get(['id', 'name', 'type', 'description', 'icon']);

        return response()->json($paymentMethods);
    }
}
