<?php

namespace App\Services;

use App\Models\Package;
use App\Models\WorkSession;
use Illuminate\Support\Collection;

/**
 * Service layer a WorkSession tablómód csomagjainak kezeléséhez (PACKAGES mód)
 */
class TabloModeService
{
    /**
     * Beállítja az elérhető csomagokat rendezett formában
     *
     * @param WorkSession $workSession - Work session példány
     * @param array $packageIds - Package ID-k tömbje [1, 3, 5]
     * @return void
     */
    public function setAllowedPackages(WorkSession $workSession, array $packageIds): void
    {
        // Ha üres a tömb, null-t mentünk
        if (empty($packageIds)) {
            $workSession->allowed_package_ids = null;
            $workSession->save();
            return;
        }

        // Betöltjük a Package-eket és rendezzük ár szerint (növekvő)
        $packages = Package::whereIn('id', $packageIds)
            ->orderBy('price', 'asc')
            ->get();

        // JSON struktúra készítése
        $allowedPackages = $packages->map(function (Package $package, int $index) {
            return [
                'package_id' => $package->id,
                'order' => $index + 1,
                'label' => $package->name,
                'is_default' => $index === 0, // Első csomag lesz az alapértelmezett
            ];
        })->toArray();

        // Menti a WorkSession-re az allowed_package_ids mezőbe
        $workSession->allowed_package_ids = $allowedPackages;
        $workSession->save();
    }

    /**
     * Visszaadja a rendezett csomagokat teljes adattal
     *
     * @param WorkSession $workSession - Work session példány
     * @return Collection - Rendezett Package collection teljes adatokkal
     */
    public function getOrderedPackages(WorkSession $workSession): Collection
    {
        // Ha nincs allowed_package_ids, üres collection-t adunk vissza
        if (empty($workSession->allowed_package_ids)) {
            return collect();
        }

        // Package ID-k kinyerése a JSON struktúrából
        $packageIds = collect($workSession->allowed_package_ids)
            ->pluck('package_id')
            ->toArray();

        // Betöltjük a kapcsolódó Package-eket
        $packages = Package::whereIn('id', $packageIds)->get()->keyBy('id');

        // Rendezzük order mező szerint és összeállítjuk a teljes adatokat
        return collect($workSession->allowed_package_ids)
            ->sortBy('order')
            ->map(function (array $item) use ($packages) {
                $package = $packages->get($item['package_id']);

                // Ha nem létezik a Package, null-t adunk vissza
                if (!$package) {
                    return null;
                }

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'photo_count' => $package->selectable_photos_count,
                    'price' => $package->price,
                    'order' => $item['order'],
                    'is_default' => $item['is_default'],
                    'label' => $item['label'],
                ];
            })
            ->filter() // Null értékek kiszűrése
            ->values(); // Újra indexelés
    }

    /**
     * Visszaadja az alapértelmezett csomagot
     *
     * @param WorkSession $workSession - Work session példány
     * @return Package|null - Alapértelmezett Package vagy null
     */
    public function getDefaultPackage(WorkSession $workSession): ?Package
    {
        // Ha nincs allowed_package_ids, null-t adunk vissza
        if (empty($workSession->allowed_package_ids)) {
            return null;
        }

        // Megkeressük az is_default: true csomagot
        $defaultItem = collect($workSession->allowed_package_ids)
            ->firstWhere('is_default', true);

        // Ha nincs default, null-t adunk vissza
        if (!$defaultItem) {
            return null;
        }

        // Visszadjuk a teljes Package model-t
        return Package::find($defaultItem['package_id']);
    }

    /**
     * Ellenőrzi, hogy egy adott csomag engedélyezett-e a work session-ben
     *
     * @param WorkSession $workSession - Work session példány
     * @param int $packageId - Package ID
     * @return bool - Engedélyezett-e
     */
    public function isPackageAllowed(WorkSession $workSession, int $packageId): bool
    {
        if (empty($workSession->allowed_package_ids)) {
            return false;
        }

        return collect($workSession->allowed_package_ids)
            ->contains('package_id', $packageId);
    }

    /**
     * Frissíti egy csomag order és label értékét
     *
     * @param WorkSession $workSession - Work session példány
     * @param int $packageId - Package ID
     * @param int|null $newOrder - Új sorrend (opcionális)
     * @param string|null $newLabel - Új címke (opcionális)
     * @return bool - Sikeres volt-e
     */
    public function updatePackageSettings(
        WorkSession $workSession,
        int $packageId,
        ?int $newOrder = null,
        ?string $newLabel = null
    ): bool {
        if (empty($workSession->allowed_package_ids)) {
            return false;
        }

        $allowedPackages = collect($workSession->allowed_package_ids)
            ->map(function (array $item) use ($packageId, $newOrder, $newLabel) {
                if ($item['package_id'] === $packageId) {
                    if ($newOrder !== null) {
                        $item['order'] = $newOrder;
                    }
                    if ($newLabel !== null) {
                        $item['label'] = $newLabel;
                    }
                }
                return $item;
            })
            ->toArray();

        $workSession->allowed_package_ids = $allowedPackages;
        $workSession->save();

        return true;
    }

    /**
     * Beállítja az alapértelmezett csomagot
     *
     * @param WorkSession $workSession - Work session példány
     * @param int $packageId - Package ID ami alapértelmezett lesz
     * @return bool - Sikeres volt-e
     */
    public function setDefaultPackage(WorkSession $workSession, int $packageId): bool
    {
        if (empty($workSession->allowed_package_ids)) {
            return false;
        }

        // Ellenőrizzük, hogy a csomag benne van-e az engedélyezettek között
        if (!$this->isPackageAllowed($workSession, $packageId)) {
            return false;
        }

        $allowedPackages = collect($workSession->allowed_package_ids)
            ->map(function (array $item) use ($packageId) {
                $item['is_default'] = ($item['package_id'] === $packageId);
                return $item;
            })
            ->toArray();

        $workSession->allowed_package_ids = $allowedPackages;
        $workSession->save();

        return true;
    }
}
