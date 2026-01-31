<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\WorkSession;
use App\Services\PricingContextService;
use Illuminate\Http\Request;

class PricingContextController extends Controller
{
    /**
     * Get pricing context for work session and album
     */
    public function index(Request $request, PricingContextService $pricingService)
    {
        $validated = $request->validate([
            'work_session_id' => 'required|integer|exists:work_sessions,id',
            'album_id' => 'required|integer|exists:albums,id',
            'current_step' => 'nullable|string|in:claiming,registration,retouch,tablo,completed',
            'context' => 'nullable|string|in:photo_selection,cart,checkout',
        ]);

        $workSession = WorkSession::find($validated['work_session_id']);
        $album = Album::find($validated['album_id']);
        $currentStep = $validated['current_step'] ?? null;
        $contextType = $validated['context'] ?? 'photo_selection';

        $context = $pricingService->resolveContext($workSession, $album);

        $package = $context['package'];
        $priceList = $context['priceList'];
        $mode = $context['mode'];

        // Build response
        $response = [
            'mode' => $mode,
            'package' => null,
            'priceList' => null,
            'availableSizes' => $pricingService->getAvailableSizes($package, $priceList)->toArray(),
            'quantitySelectorsVisible' => $pricingService->areQuantitySelectorsVisible($mode),
            'maxSelectablePhotos' => $pricingService->getMaxSelectablePhotos($mode, $package, $workSession, $currentStep, $contextType),
        ];

        // Add package data if in package mode
        if ($mode === 'package' && $package) {
            $response['package'] = [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
                'selectablePhotosCount' => $package->selectable_photos_count,
                'items' => $package->items->map(function ($item) {
                    return [
                        'printSizeId' => $item->print_size_id,
                        'size' => $item->printSize->code ?? '',
                        'quantity' => $item->quantity,
                    ];
                })->toArray(),
            ];
        }

        // Add price list data if in pricelist mode
        if ($mode === 'pricelist' && $priceList) {
            $response['priceList'] = [
                'id' => $priceList->id,
                'name' => $priceList->name,
                'prices' => $priceList->prices->map(function ($price) {
                    return [
                        'printSizeId' => $price->print_size_id,
                        'size' => $price->printSize->code ?? '',
                        'price' => $price->gross_huf,
                    ];
                })->toArray(),
            ];
        }

        return response()->json($response);
    }
}
