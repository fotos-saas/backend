<?php

namespace App\Services\Tablo;

use App\Models\TabloBadge;
use App\Models\TabloGuestSession;
use App\Models\TabloPointLog;
use App\Models\TabloUserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Badge (kitüntetés) kezelő service
 */
class BadgeService
{
    public function __construct(
        protected PointService $pointService
    ) {}

    /**
     * Automatikus badge ellenőrzés és odaítélés
     * Minden tevékenység után hívható
     */
    public function checkAndAwardBadges(int $sessionId): Collection
    {
        $session = TabloGuestSession::findOrFail($sessionId);

        // Még nem megszerzett badge-ek
        $earnedBadgeIds = TabloUserBadge::where('tablo_guest_session_id', $sessionId)
            ->pluck('tablo_badge_id')
            ->toArray();

        $availableBadges = TabloBadge::active()
            ->whereNotIn('id', $earnedBadgeIds)
            ->get();

        $newlyEarnedBadges = collect();

        foreach ($availableBadges as $badge) {
            if ($this->meetsCriteria($session, $badge)) {
                $userBadge = $this->awardBadge($session, $badge);
                $newlyEarnedBadges->push($userBadge);
            }
        }

        return $newlyEarnedBadges;
    }

    /**
     * Badge kritériumok ellenőrzése
     */
    protected function meetsCriteria(TabloGuestSession $session, TabloBadge $badge): bool
    {
        $criteria = $badge->criteria;

        // Ha üres criteria, akkor manuálisan ítéljük oda
        if (empty($criteria)) {
            return false;
        }

        // Hozzászólások száma
        if (isset($criteria['posts']) && $session->posts_count < $criteria['posts']) {
            return false;
        }

        // Válaszok száma
        if (isset($criteria['replies']) && $session->replies_count < $criteria['replies']) {
            return false;
        }

        // Kapott like-ok
        if (isset($criteria['likes_received']) && $session->likes_received < $criteria['likes_received']) {
            return false;
        }

        // Adott like-ok
        if (isset($criteria['likes_given']) && $session->likes_given < $criteria['likes_given']) {
            return false;
        }

        // Pontszám
        if (isset($criteria['points']) && $session->points < $criteria['points']) {
            return false;
        }

        // Rang szint
        if (isset($criteria['rank_level']) && $session->rank_level < $criteria['rank_level']) {
            return false;
        }

        return true;
    }

    /**
     * Badge odaítélése
     */
    protected function awardBadge(TabloGuestSession $session, TabloBadge $badge): TabloUserBadge
    {
        return DB::transaction(function () use ($session, $badge) {
            // Badge hozzáadása a userhez
            $userBadge = TabloUserBadge::create([
                'tablo_guest_session_id' => $session->id,
                'tablo_badge_id' => $badge->id,
                'earned_at' => now(),
                'is_new' => true,
            ]);

            // Pontok hozzáadása a badge jutalmáért
            if ($badge->points > 0) {
                $this->pointService->addPoints(
                    sessionId: $session->id,
                    points: $badge->points,
                    action: TabloPointLog::ACTION_BADGE,
                    relatedType: TabloBadge::class,
                    relatedId: $badge->id,
                    description: "Badge megszerzése: {$badge->name}"
                );
            }

            return $userBadge->load('badge');
        });
    }

    /**
     * User összes badge-e
     */
    public function getUserBadges(int $sessionId): Collection
    {
        return TabloUserBadge::forSession($sessionId)
            ->with('badge')
            ->orderByDesc('earned_at')
            ->get();
    }

    /**
     * Új badge-ek (még nem látta)
     */
    public function getNewBadges(int $sessionId): Collection
    {
        return TabloUserBadge::forSession($sessionId)
            ->new()
            ->with('badge')
            ->orderByDesc('earned_at')
            ->get();
    }

    /**
     * Új badge-ek megtekintése
     */
    public function markBadgesAsViewed(int $sessionId): void
    {
        TabloUserBadge::forSession($sessionId)
            ->new()
            ->update([
                'is_new' => false,
                'viewed_at' => now(),
            ]);
    }

    /**
     * Badge statisztikák (hány user szerezte meg)
     */
    public function getBadgeStats(): Collection
    {
        return TabloBadge::active()
            ->withCount('userBadges')
            ->ordered()
            ->get()
            ->map(function ($badge) {
                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'tier' => $badge->tier,
                    'earned_by' => $badge->user_badges_count,
                ];
            });
    }
}
