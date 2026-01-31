<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\PrintSize;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

class ShippingCalculatorService
{
    /**
     * Letter weight limit in grams (Hungarian Post)
     */
    private const LETTER_MAX_WEIGHT = 500;

    /**
     * Calculate total weight from order items
     *
     * @param  array|Collection  $items  Array of items with size and quantity
     * @return int Total weight in grams
     */
    public function calculateWeight($items): int
    {
        $totalWeight = 0;

        foreach ($items as $item) {
            $size = is_array($item) ? ($item['size'] ?? null) : ($item->size ?? null);
            $quantity = is_array($item) ? ($item['quantity'] ?? 0) : ($item->quantity ?? 0);

            if (! $size || ! $quantity) {
                continue;
            }

            $printSize = PrintSize::where('name', $size)->first();
            if ($printSize && $printSize->weight_grams) {
                $totalWeight += $printSize->weight_grams * $quantity;
            }
        }

        return $totalWeight;
    }

    /**
     * Check if order can be sent as letter
     *
     * @param  int  $weight  Weight in grams
     * @param  int  $quantity  Number of items
     */
    public function canSendAsLetter(int $weight, int $quantity): bool
    {
        // Check weight limit
        if ($weight > self::LETTER_MAX_WEIGHT) {
            return false;
        }

        // Check quantity (assuming max ~20 photos can fit in a letter envelope)
        if ($quantity > 20) {
            return false;
        }

        return true;
    }

    /**
     * Get available shipping methods for given weight and payment method
     */
    public function getAvailableShippingMethods(int $weightGrams, ?PaymentMethod $paymentMethod = null): Collection
    {
        $query = ShippingMethod::query()
            ->active()
            ->ordered()
            ->with('rates');

        // Filter by weight limits
        $query->forWeight($weightGrams);

        $methods = $query->get();

        // Filter out methods that don't support COD if payment method is cash
        if ($paymentMethod && $paymentMethod->isCash()) {
            $methods = $methods->filter(function ($method) {
                return $method->supports_cod;
            });
        }

        // Filter out methods that don't have a rate for this weight
        $methods = $methods->filter(function ($method) use ($weightGrams) {
            return $method->isAvailableForWeight($weightGrams);
        });

        return $methods;
    }

    /**
     * Calculate shipping cost for given method and weight
     *
     * @return int|null Cost in HUF or null if not found
     */
    public function calculateShippingCost(int $shippingMethodId, int $weightGrams): ?int
    {
        $shippingMethod = ShippingMethod::find($shippingMethodId);

        if (! $shippingMethod) {
            return null;
        }

        return $shippingMethod->getCostForWeight($weightGrams);
    }

    /**
     * Get COD fee for shipping method
     *
     * @return int COD fee in HUF
     */
    public function calculateCodFee(int $shippingMethodId): int
    {
        $shippingMethod = ShippingMethod::find($shippingMethodId);

        if (! $shippingMethod || ! $shippingMethod->supports_cod) {
            return 0;
        }

        return $shippingMethod->cod_fee_huf;
    }

    /**
     * Calculate all shipping options with prices for cart items
     *
     * @param  array|Collection  $items
     */
    public function calculateShippingOptions($items, ?PaymentMethod $paymentMethod = null): array
    {
        $weight = $this->calculateWeight($items);
        $itemCount = is_countable($items) ? count($items) : $items->count();

        $canSendAsLetter = $this->canSendAsLetter($weight, $itemCount);
        $availableMethods = $this->getAvailableShippingMethods($weight, $paymentMethod);

        $methodsWithPrices = $availableMethods->map(function ($method) use ($weight, $paymentMethod) {
            $cost = $method->getCostForWeight($weight);
            $codFee = ($paymentMethod && $paymentMethod->isCash() && $method->supports_cod)
                ? $method->cod_fee_huf
                : 0;

            return [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type,
                'provider' => $method->provider,
                'description' => $method->description,
                'requires_address' => $method->requires_address,
                'requires_parcel_point' => $method->requires_parcel_point,
                'supports_cod' => $method->supports_cod,
                'min_weight_grams' => $method->min_weight_grams,
                'max_weight_grams' => $method->max_weight_grams,
                'is_default' => $method->is_default,
                'shipping_cost_huf' => $cost,
                'cod_fee_huf' => $codFee,
                'total_cost_huf' => $cost + $codFee,
            ];
        })->values();

        // Get default shipping method ID
        $defaultMethod = ShippingMethod::query()
            ->active()
            ->default()
            ->first();

        return [
            'total_weight_grams' => $weight,
            'can_send_as_letter' => $canSendAsLetter,
            'default_shipping_method_id' => $defaultMethod?->id,
            'available_methods' => $methodsWithPrices,
        ];
    }
}
