<?php

namespace App\Services\Storage;

use App\Models\Partner;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\SubscriptionItem;

/**
 * StorageAddonService
 *
 * Extra tárhely vásárlás kezelése Stripe subscription addon-ként.
 * A storage addon quantity-based árazással működik (GB egység).
 */
final class StorageAddonService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Storage addon beállítása/frissítése.
     *
     * @param Partner $partner A partner
     * @param int $gb Extra tárhely GB-ban (0 = törlés)
     * @throws ApiErrorException Stripe hiba esetén
     */
    public function setAddonGb(Partner $partner, int $gb): void
    {
        if (! $partner->stripe_subscription_id) {
            throw new \RuntimeException('A partnernek nincs aktív Stripe előfizetése.');
        }

        $priceId = $this->getPriceId($partner);

        if ($partner->stripe_storage_addon_item_id) {
            // Létező addon frissítése
            $this->updateExistingAddon($partner, $gb, $priceId);
        } elseif ($gb > 0) {
            // Új addon létrehozása
            $this->createNewAddon($partner, $gb, $priceId);
        }

        // Lokális adatbázis frissítése
        $partner->update(['additional_storage_gb' => $gb]);

        Log::info('Storage addon updated', [
            'partner_id' => $partner->id,
            'additional_storage_gb' => $gb,
        ]);
    }

    /**
     * Storage addon törlése (0 GB-ra állítás).
     */
    public function removeAddon(Partner $partner): void
    {
        $this->setAddonGb($partner, 0);
    }

    /**
     * Havi egységár lekérdezése (Ft/GB/hó).
     */
    public function getMonthlyPrice(): int
    {
        return (int) config('plans.storage_addon.unit_price_monthly', 150);
    }

    /**
     * Éves egységár lekérdezése (Ft/GB/év - 10% kedvezmény).
     */
    public function getYearlyPrice(): int
    {
        return (int) config('plans.storage_addon.unit_price_yearly', 1620);
    }

    /**
     * Aktuális ár lekérdezése a partner billing cycle alapján.
     */
    public function getCurrentUnitPrice(Partner $partner): int
    {
        return $partner->billing_cycle === 'yearly'
            ? $this->getYearlyPrice()
            : $this->getMonthlyPrice();
    }

    /**
     * Megfelelő Stripe Price ID lekérdezése billing cycle alapján.
     */
    private function getPriceId(Partner $partner): string
    {
        $priceId = $partner->billing_cycle === 'yearly'
            ? config('stripe.storage_addon.price_id_yearly')
            : config('stripe.storage_addon.price_id_monthly');

        if (! $priceId) {
            throw new \RuntimeException('Storage addon Stripe Price ID nincs konfigurálva.');
        }

        return $priceId;
    }

    /**
     * Létező addon frissítése vagy törlése.
     */
    private function updateExistingAddon(Partner $partner, int $gb, string $priceId): void
    {
        if ($gb > 0) {
            // Quantity frissítése
            SubscriptionItem::update($partner->stripe_storage_addon_item_id, [
                'quantity' => $gb,
                'proration_behavior' => 'create_prorations',
            ]);
        } else {
            // Addon törlése
            SubscriptionItem::retrieve($partner->stripe_storage_addon_item_id)->delete();
            $partner->update(['stripe_storage_addon_item_id' => null]);
        }
    }

    /**
     * Új addon létrehozása az előfizetésen.
     */
    private function createNewAddon(Partner $partner, int $gb, string $priceId): void
    {
        $item = SubscriptionItem::create([
            'subscription' => $partner->stripe_subscription_id,
            'price' => $priceId,
            'quantity' => $gb,
            'proration_behavior' => 'create_prorations',
        ]);

        $partner->update(['stripe_storage_addon_item_id' => $item->id]);
    }
}
