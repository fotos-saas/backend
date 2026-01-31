<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Package;
use App\Models\PriceList;
use App\Models\WorkSession;
use Illuminate\Support\Collection;

class PricingContextService
{
    /**
     * Resolve pricing context based on WorkSession and Album
     *
     * Priority Logic:
     * - If WorkSession exists: ONLY use WorkSession settings (Package or PriceList), fall back to Default PriceList
     * - If no WorkSession: Use Album settings (Package or PriceList), fall back to Default PriceList
     * - This ensures WorkSession context overrides Album settings completely
     *
     * @param  WorkSession|null  $workSession  Work session context
     * @param  Album|null  $album  Album context
     * @return array{package: Package|null, priceList: PriceList|null, mode: string}
     */
    public function resolveContext(?WorkSession $workSession, ?Album $album): array
    {
        // If WorkSession exists, ONLY use WorkSession settings (ignore Album)
        if ($workSession) {
            // Priority 1: WorkSession Package
            if ($workSession->package_id) {
                return [
                    'package' => $workSession->package()->with('items.printSize')->first(),
                    'priceList' => null,
                    'mode' => 'package',
                ];
            }

            // Priority 2: WorkSession PriceList
            if ($workSession->price_list_id) {
                return [
                    'package' => null,
                    'priceList' => $workSession->priceList()->with('prices.printSize')->first(),
                    'mode' => 'pricelist',
                ];
            }

            // WorkSession exists but no pricing settings -> use default pricelist
            $defaultPriceList = PriceList::default()->with('prices.printSize')->first();

            return [
                'package' => null,
                'priceList' => $defaultPriceList,
                'mode' => 'pricelist',
            ];
        }

        // No WorkSession context: use Album settings
        // Priority 3: Album Package
        if ($album && $album->package_id) {
            return [
                'package' => $album->package()->with('items.printSize')->first(),
                'priceList' => null,
                'mode' => 'package',
            ];
        }

        // Priority 4: Album PriceList
        if ($album && $album->price_list_id) {
            return [
                'package' => null,
                'priceList' => $album->priceList()->with('prices.printSize')->first(),
                'mode' => 'pricelist',
            ];
        }

        // Priority 5: Default PriceList (no WorkSession, no Album settings)
        $defaultPriceList = PriceList::default()->with('prices.printSize')->first();

        return [
            'package' => null,
            'priceList' => $defaultPriceList,
            'mode' => 'pricelist',
        ];
    }

    /**
     * Get remaining photo count for package mode
     *
     * @param  Package|null  $package  Package context
     * @param  int  $currentlySelected  Currently selected photos count
     * @return int|null Remaining count or null if no limit
     */
    public function getRemainingPhotoCount(?Package $package, int $currentlySelected): ?int
    {
        if (! $package) {
            return null;
        }

        return max(0, $package->selectable_photos_count - $currentlySelected);
    }

    /**
     * Get available sizes based on package or price list
     *
     * @param  Package|null  $package  Package context
     * @param  PriceList|null  $priceList  Price list context
     * @return Collection Collection of size codes
     */
    public function getAvailableSizes(?Package $package, ?PriceList $priceList): Collection
    {
        if ($package) {
            // Package mode: get sizes from package items
            return $package->items()
                ->with('printSize')
                ->get()
                ->pluck('printSize.code')
                ->unique()
                ->values();
        }

        if ($priceList) {
            // Price list mode: get sizes from prices
            return $priceList->prices()
                ->with('printSize')
                ->get()
                ->pluck('printSize.code')
                ->unique()
                ->values();
        }

        return collect([]);
    }

    /**
     * Check if quantity selectors should be visible
     *
     * @param  string  $mode  Mode (package or pricelist)
     * @return bool True if quantity selectors should be visible
     */
    public function areQuantitySelectorsVisible(string $mode): bool
    {
        return $mode === 'pricelist';
    }

    /**
     * Get max selectable photos
     *
     * Priority: WorkSession max_retouch_photos > Package selectable_photos_count > null
     * In tablo mode, limits vary by step: claiming (unlimited) > retouch (max_retouch_photos) > tablo (1)
     *
     * @param  string  $mode  Mode (package or pricelist)
     * @param  Package|null  $package  Package context
     * @param  WorkSession|null  $workSession  Work session context
     * @param  string|null  $currentStep  Current tablo workflow step (claiming, retouch, tablo)
     * @param  string  $context  Context where pricing is used (photo_selection, cart, checkout)
     * @return int|null Max photos or null if no limit
     */
    public function getMaxSelectablePhotos(string $mode, ?Package $package, ?WorkSession $workSession = null, ?string $currentStep = null, string $context = 'photo_selection'): ?int
    {
        // Cart and checkout contexts should NOT apply step-based limits
        // Users should see all their selected photos regardless of current step
        if (in_array($context, ['cart', 'checkout'])) {
            // Only apply package limits in cart/checkout, not tablo step limits
            if ($mode === 'package' && $package) {
                return $package->selectable_photos_count;
            }

            return null;
        }

        // Photo selection context: Apply step-based limits for tablo mode
        if ($currentStep) {
            switch ($currentStep) {
                case 'claiming':
                    // Step 1: Unlimited selection for claiming photos
                    return null;

                case 'retouch':
                    // Step 2: Limited to max_retouch_photos
                    if ($workSession && $workSession->max_retouch_photos !== null) {
                        return $workSession->max_retouch_photos;
                    }
                    break;

                case 'tablo':
                    // Step 3: Only 1 photo for final tablo selection
                    return 1;
            }
        }

        // Default behavior (non-tablo mode or no step specified)
        // Priority 1: WorkSession max_retouch_photos (overrides everything, including package)
        if ($workSession && $workSession->max_retouch_photos !== null) {
            return $workSession->max_retouch_photos;
        }

        // Priority 2: Package selectable_photos_count
        if ($mode === 'package' && $package) {
            return $package->selectable_photos_count;
        }

        return null;
    }
}
