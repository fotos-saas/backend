<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalculatePricingRequest;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\PrintSize;

class PricingController extends Controller
{
    /**
     * Get all pricing rules
     */
    public function index()
    {
        $printSizes = PrintSize::with('prices')->get();

        // Get default price list and its default print size
        $defaultPriceList = PriceList::where('is_default', true)
            ->with('defaultPrintSize')
            ->first();

        $defaultPrintSize = $defaultPriceList?->getDefaultPrintSize();

        $rules = $printSizes->map(function ($size) {
            $price = $size->prices->first();

            return [
                'id' => $price?->id ?? $size->id,
                'size' => $size->name,
                'price' => $price?->price ?? 0,
                'currency' => 'HUF',
                'volumeDiscounts' => $price?->volume_discounts ?? [],
            ];
        })->filter(fn ($item) => $item['price'] > 0);

        return response()->json([
            'rules' => $rules->values(),
            'defaultPrintSize' => $defaultPrintSize?->name,
            'defaultPrintSizeId' => $defaultPrintSize?->id,
        ]);
    }

    /**
     * Calculate cart price
     */
    public function calculate(CalculatePricingRequest $request)
    {
        $validated = $request->validated();

        $subtotal = 0;
        $itemsWithPrices = [];

        foreach ($validated['items'] as $item) {
            // Find price for size
            $printSize = PrintSize::where('name', $item['size'])->first();
            if (! $printSize) {
                continue;
            }

            $price = Price::where('print_size_id', $printSize->id)->first();

            if (! $price) {
                continue;
            }

            $unitPrice = $price->calculatePriceForQuantity($item['quantity']);
            $itemTotal = $unitPrice * $item['quantity'];
            $subtotal += $itemTotal;

            $itemsWithPrices[] = [
                'photoId' => $item['photoId'],
                'size' => $item['size'],
                'quantity' => $item['quantity'],
                'unitPrice' => $unitPrice,
                'total' => $itemTotal,
            ];
        }

        return response()->json([
            'items' => $itemsWithPrices,
            'subtotal' => $subtotal,
            'discounts' => [],
            'total' => $subtotal,
        ]);
    }
}
