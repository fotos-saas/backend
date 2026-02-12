<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPointLog;
use App\Support\GamificationRankConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pontrendszer kezelő service
 */
class PointService
{
    /**
     * Pontok hozzáadása
     */
    public function addPoints(
        int $sessionId,
        int $points,
        string $action,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $description = null
    ): void {
        DB::transaction(function () use ($sessionId, $points, $action, $relatedType, $relatedId, $description) {
            // Session frissítése
            $session = TabloGuestSession::lockForUpdate()->findOrFail($sessionId);

            $session->increment('points', $points);

            // Rang szint frissítése
            $this->updateRankLevel($session);

            // Pont napló rögzítése
            TabloPointLog::create([
                'tablo_guest_session_id' => $sessionId,
                'action' => $action,
                'points' => $points,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'description' => $description ?? $this->getDefaultDescription($action, $points),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Új hozzászólás pontok
     */
    public function addPostPoints(int $sessionId, int $postId): void
    {
        $this->addPoints(
            sessionId: $sessionId,
            points: TabloPointLog::POINTS[TabloPointLog::ACTION_POST],
            action: TabloPointLog::ACTION_POST,
            relatedType: 'App\\Models\\TabloDiscussionPost',
            relatedId: $postId,
            description: 'Új hozzászólás létrehozása'
        );

        // posts_count cache frissítése
        TabloGuestSession::where('id', $sessionId)
            ->increment('posts_count');
    }

    /**
     * Válasz pontok
     */
    public function addReplyPoints(int $sessionId, int $replyId): void
    {
        $this->addPoints(
            sessionId: $sessionId,
            points: TabloPointLog::POINTS[TabloPointLog::ACTION_REPLY],
            action: TabloPointLog::ACTION_REPLY,
            relatedType: 'App\\Models\\TabloDiscussionPost',
            relatedId: $replyId,
            description: 'Válasz írása'
        );

        // replies_count cache frissítése
        TabloGuestSession::where('id', $sessionId)
            ->increment('replies_count');
    }

    /**
     * Like kapása pontok
     */
    public function addLikeReceivedPoints(int $sessionId, int $likeId): void
    {
        $this->addPoints(
            sessionId: $sessionId,
            points: TabloPointLog::POINTS[TabloPointLog::ACTION_LIKE_RECEIVED],
            action: TabloPointLog::ACTION_LIKE_RECEIVED,
            relatedType: 'App\\Models\\TabloPostLike',
            relatedId: $likeId,
            description: 'Like kapása'
        );

        // likes_received cache frissítése
        TabloGuestSession::where('id', $sessionId)
            ->increment('likes_received');
    }

    /**
     * Like adása pontok
     */
    public function addLikeGivenPoints(int $sessionId, int $likeId): void
    {
        $this->addPoints(
            sessionId: $sessionId,
            points: TabloPointLog::POINTS[TabloPointLog::ACTION_LIKE_GIVEN],
            action: TabloPointLog::ACTION_LIKE_GIVEN,
            relatedType: 'App\\Models\\TabloPostLike',
            relatedId: $likeId,
            description: 'Like adása'
        );

        // likes_given cache frissítése
        TabloGuestSession::where('id', $sessionId)
            ->increment('likes_given');
    }

    /**
     * Rang szint frissitese pontszam alapjan
     */
    protected function updateRankLevel(TabloGuestSession $session): void
    {
        $newRankLevel = GamificationRankConfig::calculateRankLevel($session->points);

        if ($newRankLevel !== $session->rank_level) {
            $session->update(['rank_level' => $newRankLevel]);
        }
    }

    /**
     * Alapértelmezett leírás generálása
     */
    protected function getDefaultDescription(string $action, int $points): string
    {
        return match ($action) {
            TabloPointLog::ACTION_POST => "Új hozzászólás (+{$points} pont)",
            TabloPointLog::ACTION_REPLY => "Válasz írása (+{$points} pont)",
            TabloPointLog::ACTION_LIKE_RECEIVED => "Like kapása (+{$points} pont)",
            TabloPointLog::ACTION_LIKE_GIVEN => "Like adása (+{$points} pont)",
            TabloPointLog::ACTION_BADGE => "Badge megszerzése (+{$points} pont)",
            default => "Pontszám változás (+{$points} pont)",
        };
    }

    /**
     * User pontszám előzményei
     */
    public function getPointHistory(int $sessionId, int $limit = 50): Collection
    {
        return TabloPointLog::forSession($sessionId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * User összesített statisztikák
     */
    public function getUserStats(int $sessionId): array
    {
        $session = TabloGuestSession::findOrFail($sessionId);

        return [
            'total_points' => $session->points,
            'rank_level' => $session->rank_level,
            'rank_name' => $session->rank_name,
            'next_rank_points' => $session->next_rank_points,
            'progress_to_next_rank' => $session->progress_to_next_rank,
            'stats' => [
                'posts' => $session->posts_count,
                'replies' => $session->replies_count,
                'likes_received' => $session->likes_received,
                'likes_given' => $session->likes_given,
            ],
        ];
    }
}
