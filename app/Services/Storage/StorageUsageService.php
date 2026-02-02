<?php

namespace App\Services\Storage;

use App\Models\Partner;
use App\Models\PartnerAlbum;
use App\Models\TabloProject;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * StorageUsageService
 *
 * Partner tárhely használat számítása Spatie MediaLibrary-ból.
 * Összesíti a TabloProject és PartnerAlbum média fájlok méretét.
 */
final class StorageUsageService
{
    /**
     * Partner tárhely használat byte-ban.
     */
    public function getUsageBytes(Partner $partner): int
    {
        $tabloPartnerId = $this->getTabloPartnerId($partner);

        if (! $tabloPartnerId) {
            return 0;
        }

        $projectUsage = $this->getProjectMediaUsage($tabloPartnerId);
        $albumUsage = $this->getAlbumMediaUsage($tabloPartnerId);

        return $projectUsage + $albumUsage;
    }

    /**
     * Partner tárhely használat GB-ban (2 tizedesjegyre kerekítve).
     */
    public function getUsageGb(Partner $partner): float
    {
        return round($this->getUsageBytes($partner) / (1024 * 1024 * 1024), 2);
    }

    /**
     * Partner tárhely használat százalékban (1 tizedesjegyre kerekítve).
     */
    public function getUsagePercent(Partner $partner): float
    {
        $limit = $partner->getTotalStorageLimitGb();

        if ($limit <= 0) {
            return 0;
        }

        return round(($this->getUsageGb($partner) / $limit) * 100, 1);
    }

    /**
     * Közel van-e a partner a tárhely limithez?
     *
     * @param int $threshold Százalékos küszöbérték (default: 80%)
     */
    public function isNearLimit(Partner $partner, int $threshold = 80): bool
    {
        return $this->getUsagePercent($partner) >= $threshold;
    }

    /**
     * Partner összes tárhely statisztika.
     */
    public function getUsageStats(Partner $partner): array
    {
        $usedGb = $this->getUsageGb($partner);
        $planLimitGb = $partner->getPlanStorageLimitGb();
        $additionalGb = $partner->additional_storage_gb ?? 0;
        $totalLimitGb = $partner->getTotalStorageLimitGb();
        $usagePercent = $totalLimitGb > 0 ? round(($usedGb / $totalLimitGb) * 100, 1) : 0;

        return [
            'used_gb' => $usedGb,
            'plan_limit_gb' => $planLimitGb,
            'additional_gb' => $additionalGb,
            'total_limit_gb' => $totalLimitGb,
            'usage_percent' => $usagePercent,
            'is_near_limit' => $usagePercent >= 80,
        ];
    }

    /**
     * Partner TabloPartner ID lekérdezése a User-en keresztül.
     */
    private function getTabloPartnerId(Partner $partner): ?int
    {
        return $partner->user?->tablo_partner_id;
    }

    /**
     * TabloProject médiák mérete összesen (byte).
     */
    private function getProjectMediaUsage(int $tabloPartnerId): int
    {
        return (int) Media::query()
            ->where('model_type', TabloProject::class)
            ->whereIn('model_id', function ($query) use ($tabloPartnerId) {
                $query->select('id')
                    ->from('tablo_projects')
                    ->where('partner_id', $tabloPartnerId);
            })
            ->sum('size');
    }

    /**
     * PartnerAlbum médiák mérete összesen (byte).
     */
    private function getAlbumMediaUsage(int $tabloPartnerId): int
    {
        return (int) Media::query()
            ->where('model_type', PartnerAlbum::class)
            ->whereIn('model_id', function ($query) use ($tabloPartnerId) {
                $query->select('id')
                    ->from('partner_albums')
                    ->where('tablo_partner_id', $tabloPartnerId);
            })
            ->sum('size');
    }
}
