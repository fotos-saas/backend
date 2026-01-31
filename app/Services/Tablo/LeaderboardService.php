<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPointLog;
use App\Models\TabloUserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard (toplista) service
 */
class LeaderboardService
{
    /**
     * Toplista cache kulcs
     */
    protected const CACHE_KEY = 'tablo:leaderboard';

    /**
     * Cache TTL (másodperc)
     */
    protected const CACHE_TTL = 300; // 5 perc

    /**
     * Top felhasználók (összes pont alapján)
     */
    public function getTopByPoints(int $projectId, int $limit = 10): Collection
    {
        return Cache::remember(
            self::CACHE_KEY.":points:{$projectId}:{$limit}",
            self::CACHE_TTL,
            function () use ($projectId, $limit) {
                return TabloGuestSession::select([
                    'id',
                    'session_token',
                    'guest_name',
                    'points',
                    'rank_level',
                    'posts_count',
                    'replies_count',
                    'likes_received',
                ])
                    ->where('tablo_project_id', $projectId)
                    ->where('points', '>', 0)
                    ->orderByDesc('points')
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(function ($session, $index) {
                        return [
                            'rank' => $index + 1,
                            'guest_name' => $session->guest_name,
                            'points' => $session->points,
                            'rank_level' => $session->rank_level,
                            'rank_name' => $session->rank_name,
                            'stats' => [
                                'posts' => $session->posts_count,
                                'replies' => $session->replies_count,
                                'likes' => $session->likes_received,
                            ],
                        ];
                    });
            }
        );
    }

    /**
     * Top felhasználók (hozzászólások száma alapján)
     */
    public function getTopByPosts(int $projectId, int $limit = 10): Collection
    {
        return Cache::remember(
            self::CACHE_KEY.":posts:{$projectId}:{$limit}",
            self::CACHE_TTL,
            function () use ($projectId, $limit) {
                return TabloGuestSession::select([
                    'id',
                    'guest_name',
                    'posts_count',
                    'points',
                    'rank_level',
                ])
                    ->where('tablo_project_id', $projectId)
                    ->where('posts_count', '>', 0)
                    ->orderByDesc('posts_count')
                    ->limit($limit)
                    ->get()
                    ->map(function ($session, $index) {
                        return [
                            'rank' => $index + 1,
                            'guest_name' => $session->guest_name,
                            'posts_count' => $session->posts_count,
                            'points' => $session->points,
                            'rank_name' => $session->rank_name,
                        ];
                    });
            }
        );
    }

    /**
     * Top felhasználók (like-ok száma alapján)
     */
    public function getTopByLikes(int $projectId, int $limit = 10): Collection
    {
        return Cache::remember(
            self::CACHE_KEY.":likes:{$projectId}:{$limit}",
            self::CACHE_TTL,
            function () use ($projectId, $limit) {
                return TabloGuestSession::select([
                    'id',
                    'guest_name',
                    'likes_received',
                    'points',
                    'rank_level',
                ])
                    ->where('tablo_project_id', $projectId)
                    ->where('likes_received', '>', 0)
                    ->orderByDesc('likes_received')
                    ->limit($limit)
                    ->get()
                    ->map(function ($session, $index) {
                        return [
                            'rank' => $index + 1,
                            'guest_name' => $session->guest_name,
                            'likes_received' => $session->likes_received,
                            'points' => $session->points,
                            'rank_name' => $session->rank_name,
                        ];
                    });
            }
        );
    }

    /**
     * User pozíciója a toplistán
     */
    public function getUserRank(int $projectId, int $sessionId): ?array
    {
        $session = TabloGuestSession::where('id', $sessionId)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session || $session->points === 0) {
            return null;
        }

        // Hányan vannak előtte (több ponttal)?
        $rank = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('points', '>', $session->points)
            ->count() + 1;

        // Összes aktív user
        $totalUsers = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('points', '>', 0)
            ->count();

        // Badge-ek száma
        $badgesCount = TabloUserBadge::where('tablo_guest_session_id', $sessionId)->count();

        return [
            'rank' => $rank,
            'total_users' => $totalUsers,
            'percentile' => $totalUsers > 0 ? round((1 - ($rank / $totalUsers)) * 100, 1) : 0,
            'points' => $session->points,
            'rank_level' => $session->rank_level,
            'rank_name' => $session->rank_name,
            'badges_count' => $badgesCount,
        ];
    }

    /**
     * Toplista cache törlése (pont változás után hívni)
     */
    public function clearCache(int $projectId): void
    {
        Cache::forget(self::CACHE_KEY.":points:{$projectId}:10");
        Cache::forget(self::CACHE_KEY.":posts:{$projectId}:10");
        Cache::forget(self::CACHE_KEY.":likes:{$projectId}:10");
        Cache::forget(self::CACHE_KEY.":weekly:{$projectId}:5");
    }

    /**
     * Heti top felhasználók
     */
    public function getWeeklyTop(int $projectId, int $limit = 5): Collection
    {
        $weekAgo = now()->subWeek();

        return Cache::remember(
            self::CACHE_KEY.":weekly:{$projectId}:{$limit}",
            self::CACHE_TTL,
            function () use ($projectId, $weekAgo, $limit) {
                // Session-ök, akik az elmúlt héten pontot szereztek
                $sessionPoints = DB::table('tablo_point_logs')
                    ->join('tablo_guest_sessions', 'tablo_point_logs.tablo_guest_session_id', '=', 'tablo_guest_sessions.id')
                    ->where('tablo_guest_sessions.tablo_project_id', $projectId)
                    ->where('tablo_point_logs.created_at', '>=', $weekAgo)
                    ->groupBy('tablo_point_logs.tablo_guest_session_id')
                    ->selectRaw('tablo_point_logs.tablo_guest_session_id, SUM(tablo_point_logs.points) as weekly_points')
                    ->orderByDesc('weekly_points')
                    ->limit($limit)
                    ->pluck('weekly_points', 'tablo_guest_session_id');

                if ($sessionPoints->isEmpty()) {
                    return collect();
                }

                return TabloGuestSession::whereIn('id', $sessionPoints->keys())
                    ->get()
                    ->map(function ($session) use ($sessionPoints) {
                        return [
                            'guest_name' => $session->guest_name,
                            'weekly_points' => $sessionPoints[$session->id],
                            'total_points' => $session->points,
                            'rank_name' => $session->rank_name,
                        ];
                    })
                    ->sortByDesc('weekly_points')
                    ->values();
            }
        );
    }
}
