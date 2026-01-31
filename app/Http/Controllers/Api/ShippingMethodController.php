<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\ShippingCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items');
        $paymentMethodId = $request->input('payment_method_id');

        $paymentMethod = $paymentMethodId ? PaymentMethod::find($paymentMethodId) : null;

        $result = $this->shippingCalculator->calculateShippingOptions($items, $paymentMethod);

        return response()->json($result);
    }
}
