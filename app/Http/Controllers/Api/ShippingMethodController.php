<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalculateShippingRequest;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\ShippingCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function __construct(
        private ShippingCalculatorService $shippingCalculator
    ) {}

    /**
     * Get all active shipping methods
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShippingMethod::active()->ordered()->with('rates');

        // Optional weight filter
        if ($request->has('weight')) {
            $weight = (int) $request->input('weight');
            $query->forWeight($weight);
        }

        $shippingMethods = $query->get();

        return response()->json($shippingMethods);
    }

    /**
     * Calculate available shipping methods with prices
     */
    public function calculate(CalculateShippingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];
        $paymentMethodId = $validated['payment_method_id'] ?? null;

        $paymentMethod = $paymentMethodId ? PaymentMethod::find($paymentMethodId) : null;

        $result = $this->shippingCalculator->calculateShippingOptions($items, $paymentMethod);

        return response()->json($result);
    }
}
