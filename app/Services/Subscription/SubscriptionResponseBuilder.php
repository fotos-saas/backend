<?php

namespace App\Services\Subscription;

use App\Models\Partner;
use App\Models\TabloProject;
use App\Models\TabloSchool;

class SubscriptionResponseBuilder
{
    /**
     * Build subscription response for a partner.
     */
    public function build(Partner $partner): array
    {
        $planConfig = config("plans.plans.{$partner->plan}");
        $storageAddonConfig = config('plans.storage_addon');
        $addonsConfig = config('plans.addons');

        $usage = $this->getUsageStats($partner->user->tablo_partner_id ?? null);

        $hasExtraStorage = ($partner->extra_storage_gb ?? 0) > 0;
        $activeAddons = $partner->addons;
        $hasAddons = $activeAddons->count() > 0;

        $addonPrices = [];
        foreach ($activeAddons as $addon) {
            $addonConfig = $addonsConfig[$addon->addon_key] ?? null;
            if ($addonConfig) {
                $addonPrices[$addon->addon_key] = [
                    'monthly' => $addonConfig['monthly_price'] ?? 0,
                    'yearly' => $addonConfig['yearly_price'] ?? 0,
                ];
            }
        }

        return [
            'partner_name' => $partner->company_name,
            'plan' => $partner->plan,
            'plan_name' => $planConfig['name'] ?? $partner->plan,
            'billing_cycle' => $partner->billing_cycle,
            'status' => $partner->subscription_status,
            'started_at' => $partner->subscription_started_at,
            'ends_at' => $partner->subscription_ends_at,
            'features' => $planConfig['feature_labels'] ?? [],
            'limits' => $planConfig['limits'] ?? [],
            'usage' => $usage,
            'is_modified' => $hasExtraStorage || $hasAddons,
            'has_extra_storage' => $hasExtraStorage,
            'extra_storage_gb' => $partner->extra_storage_gb ?? 0,
            'has_addons' => $hasAddons,
            'active_addons' => $activeAddons->pluck('addon_key')->toArray(),
            'prices' => [
                'plan_monthly' => $planConfig['monthly_price'] ?? 0,
                'plan_yearly' => $planConfig['yearly_price'] ?? 0,
                'storage_monthly' => $storageAddonConfig['unit_price_monthly'] ?? 150,
                'storage_yearly' => $storageAddonConfig['unit_price_yearly'] ?? 1620,
                'addons' => $addonPrices,
            ],
        ];
    }

    private function getUsageStats(?int $tabloPartnerId): array
    {
        if (! $tabloPartnerId) {
            return ['schools' => 0, 'classes' => 0, 'templates' => 0];
        }

        return [
            'schools' => TabloSchool::whereHas('projects', fn ($q) => $q->where('partner_id', $tabloPartnerId))->count(),
            'classes' => TabloProject::where('partner_id', $tabloPartnerId)->count(),
            'templates' => 0,
        ];
    }
}
