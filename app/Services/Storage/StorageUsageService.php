<?php

namespace App\Services\Storage;

use App\Models\Partner;
use App\Models\PartnerAlbum;
use App\Models\TabloGallery;
use App\Models\TabloProject;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * StorageUsageService
 *
 * Partner tárhely használat számítása.
 * Források: TabloProject média, PartnerAlbum média, TabloGallery média,
 * newsfeed média, discussion post média, poll média.
 *
 * Cache: partners.storage_used_bytes + storage_calculated_at (2 órás TTL).
 */
final class StorageUsageService
{
    private const CACHE_TTL_HOURS = 2;

    /**
     * Partner tárhely használat byte-ban (cache-elt).
     */
    public function getUsageBytes(Partner $partner): int
    {
        if ($this->isCacheValid($partner)) {
            return (int) $partner->storage_used_bytes;
        }

        return $this->recalculateAndCache($partner);
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
     * Partner összes tárhely statisztika (cache-elt).
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
     * Újraszámolja és cache-eli a partner tárhely használatát.
     */
    public function recalculateAndCache(Partner $partner): int
    {
        $tabloPartnerId = $this->getTabloPartnerId($partner);

        if (! $tabloPartnerId) {
            $partner->update([
                'storage_used_bytes' => 0,
                'storage_calculated_at' => now(),
            ]);

            return 0;
        }

        $totalBytes = $this->getProjectMediaUsage($tabloPartnerId)
            + $this->getAlbumMediaUsage($tabloPartnerId)
            + $this->getGalleryMediaUsage($tabloPartnerId)
            + $this->getNewsfeedMediaUsage($tabloPartnerId)
            + $this->getDiscussionPostMediaUsage($tabloPartnerId)
            + $this->getPollMediaUsage($tabloPartnerId);

        $partner->update([
            'storage_used_bytes' => $totalBytes,
            'storage_calculated_at' => now(),
        ]);

        return $totalBytes;
    }

    /**
     * Cache érvényes-e?
     */
    private function isCacheValid(Partner $partner): bool
    {
        return $partner->storage_used_bytes !== null
            && $partner->storage_calculated_at !== null
            && $partner->storage_calculated_at->diffInHours(now()) < self::CACHE_TTL_HOURS;
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

    /**
     * TabloGallery médiák mérete összesen (byte).
     * Lánc: media → tablo_galleries → tablo_projects.partner_id
     */
    private function getGalleryMediaUsage(int $tabloPartnerId): int
    {
        return (int) Media::query()
            ->where('model_type', TabloGallery::class)
            ->whereIn('model_id', function ($query) use ($tabloPartnerId) {
                $query->select('tablo_gallery_id')
                    ->from('tablo_projects')
                    ->where('partner_id', $tabloPartnerId)
                    ->whereNotNull('tablo_gallery_id');
            })
            ->sum('size');
    }

    /**
     * Newsfeed post médiák mérete összesen (byte).
     * Lánc: tablo_newsfeed_media → tablo_newsfeed_posts → tablo_projects.partner_id
     */
    private function getNewsfeedMediaUsage(int $tabloPartnerId): int
    {
        return (int) DB::table('tablo_newsfeed_media')
            ->whereIn('tablo_newsfeed_post_id', function ($query) use ($tabloPartnerId) {
                $query->select('id')
                    ->from('tablo_newsfeed_posts')
                    ->whereIn('tablo_project_id', function ($sub) use ($tabloPartnerId) {
                        $sub->select('id')
                            ->from('tablo_projects')
                            ->where('partner_id', $tabloPartnerId);
                    });
            })
            ->sum('file_size');
    }

    /**
     * Discussion post médiák mérete összesen (byte).
     * Lánc: tablo_post_media → tablo_discussion_posts → tablo_discussions → tablo_projects.partner_id
     */
    private function getDiscussionPostMediaUsage(int $tabloPartnerId): int
    {
        return (int) DB::table('tablo_post_media')
            ->whereIn('tablo_discussion_post_id', function ($query) use ($tabloPartnerId) {
                $query->select('id')
                    ->from('tablo_discussion_posts')
                    ->whereIn('tablo_discussion_id', function ($sub) use ($tabloPartnerId) {
                        $sub->select('id')
                            ->from('tablo_discussions')
                            ->whereIn('tablo_project_id', function ($sub2) use ($tabloPartnerId) {
                                $sub2->select('id')
                                    ->from('tablo_projects')
                                    ->where('partner_id', $tabloPartnerId);
                            });
                    });
            })
            ->sum('file_size');
    }

    /**
     * Poll médiák mérete összesen (byte).
     * Lánc: tablo_poll_media → tablo_polls → tablo_projects.partner_id
     */
    private function getPollMediaUsage(int $tabloPartnerId): int
    {
        return (int) DB::table('tablo_poll_media')
            ->whereIn('tablo_poll_id', function ($query) use ($tabloPartnerId) {
                $query->select('id')
                    ->from('tablo_polls')
                    ->whereIn('tablo_project_id', function ($sub) use ($tabloPartnerId) {
                        $sub->select('id')
                            ->from('tablo_projects')
                            ->where('partner_id', $tabloPartnerId);
                    });
            })
            ->sum('file_size');
    }
}
