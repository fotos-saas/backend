<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloGuestSession;
use App\Services\Tablo\BadgeService;
use App\Services\Tablo\LeaderboardService;
use App\Services\Tablo\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gamification Controller
 *
 * Pontok, badge-ek, rangok és leaderboard API végpontok.
 */
class GamificationController extends Controller
{
    public function __construct(
        protected PointService $pointService,
        protected BadgeService $badgeService,
        protected LeaderboardService $leaderboardService
    ) {}

    /**
     * Get user stats (points, rank, progress).
     * GET /api/tablo-frontend/gamification/stats
     */
    public function stats(Request $request): JsonResponse
    {
        [$guestSession, $errorResponse] = $this->resolveGuestOrFail($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        return response()->json([
            'success' => true,
            'data' => $this->pointService->getUserStats($guestSession->id),
        ]);
    }

    /**
     * Get user badges.
     * GET /api/tablo-frontend/gamification/badges
     */
    public function badges(Request $request): JsonResponse
    {
        [$guestSession, $errorResponse] = $this->resolveGuestOrFail($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $badges = $this->badgeService->getUserBadges($guestSession->id);
        $newBadges = $this->badgeService->getNewBadges($guestSession->id);

        return response()->json([
            'success' => true,
            'data' => [
                'badges' => $badges->map(fn ($ub) => $this->formatUserBadge($ub)),
                'new_badges' => $newBadges->map(fn ($ub) => $this->formatUserBadge($ub)),
            ],
        ]);
    }

    /**
     * Mark badges as viewed.
     * POST /api/tablo-frontend/gamification/badges/viewed
     */
    public function markBadgesViewed(Request $request): JsonResponse
    {
        [$guestSession, $errorResponse] = $this->resolveGuestOrFail($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $this->badgeService->markBadgesAsViewed($guestSession->id);

        return response()->json([
            'success' => true,
            'message' => 'Badge-ek megtekintettnek jelölve.',
        ]);
    }

    /**
     * Get user rank on leaderboard.
     * GET /api/tablo-frontend/gamification/rank
     */
    public function rank(Request $request): JsonResponse
    {
        [$guestSession, $errorResponse, $projectId] = $this->resolveGuestOrFail($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        return response()->json([
            'success' => true,
            'data' => $this->leaderboardService->getUserRank($projectId, $guestSession->id),
        ]);
    }

    /**
     * Get leaderboard.
     * GET /api/tablo-frontend/gamification/leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $projectId = $request->user()->currentAccessToken()->tablo_project_id;

        $type = $request->get('type', 'points');
        $limit = $request->integer('limit', 10);

        $entries = match ($type) {
            'posts' => $this->leaderboardService->getTopByPosts($projectId, $limit),
            'likes' => $this->leaderboardService->getTopByLikes($projectId, $limit),
            default => $this->leaderboardService->getTopByPoints($projectId, $limit),
        };

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Get weekly top users.
     * GET /api/tablo-frontend/gamification/leaderboard/weekly
     */
    public function weeklyLeaderboard(Request $request): JsonResponse
    {
        $projectId = $request->user()->currentAccessToken()->tablo_project_id;
        $limit = $request->integer('limit', 5);

        return response()->json([
            'success' => true,
            'data' => $this->leaderboardService->getWeeklyTop($projectId, $limit),
        ]);
    }

    /**
     * Get point history.
     * GET /api/tablo-frontend/gamification/points/history
     */
    public function pointHistory(Request $request): JsonResponse
    {
        [$guestSession, $errorResponse] = $this->resolveGuestOrFail($request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $limit = $request->integer('limit', 50);
        $history = $this->pointService->getPointHistory($guestSession->id, $limit);

        return response()->json([
            'success' => true,
            'data' => $history->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'points' => $log->points,
                'description' => $log->description,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Resolve guest session from request token, or return error response.
     *
     * @return array{0: ?TabloGuestSession, 1: ?JsonResponse, 2: ?int}
     */
    private function resolveGuestOrFail(Request $request): array
    {
        $projectId = $request->user()->currentAccessToken()->tablo_project_id;
        $guestSessionToken = $request->header('X-Guest-Session');

        if (! $guestSessionToken) {
            return [null, response()->json(['success' => false, 'message' => 'Érvénytelen session.'], 401), $projectId];
        }

        $guestSession = TabloGuestSession::findByTokenAndProject($guestSessionToken, $projectId);

        if (! $guestSession || $guestSession->is_banned) {
            return [null, response()->json(['success' => false, 'message' => 'Érvénytelen session.'], 401), $projectId];
        }

        return [$guestSession, null, $projectId];
    }

    /**
     * Format user badge for API response.
     */
    private function formatUserBadge($userBadge): array
    {
        return [
            'id' => $userBadge->id,
            'badge' => [
                'id' => $userBadge->badge->id,
                'key' => $userBadge->badge->key,
                'name' => $userBadge->badge->name,
                'description' => $userBadge->badge->description,
                'tier' => $userBadge->badge->tier,
                'icon' => $userBadge->badge->icon,
                'color' => $userBadge->badge->color,
                'points' => $userBadge->badge->points,
            ],
            'earned_at' => $userBadge->earned_at->toIso8601String(),
            'is_new' => $userBadge->is_new,
        ];
    }
}
