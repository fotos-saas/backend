<?php

namespace App\Support;

/**
 * Gamification rang konfiguracio (SINGLE SOURCE OF TRUTH).
 *
 * Rang szintek es ponthatarjok, amik korabban duplikaltva voltak:
 * - TabloGuestSession model (getRankNameAttribute, getNextRankPointsAttribute, getProgressToNextRankAttribute)
 * - PointService (updateRankLevel)
 */
class GamificationRankConfig
{
    /**
     * Rang szintek minimalis pontszamai
     */
    public const RANK_THRESHOLDS = [
        1 => 0,
        2 => 25,
        3 => 100,
        4 => 250,
        5 => 500,
        6 => 1000,
    ];

    /**
     * Rang nevek (magyar)
     */
    public const RANK_NAMES = [
        1 => 'Ujonc',
        2 => 'Tag',
        3 => 'Aktiv tag',
        4 => 'Veteran',
        5 => 'Mester',
        6 => 'Legenda',
    ];

    /**
     * Rang nev lekerese szint alapjan
     */
    public static function getRankName(int $level): string
    {
        return self::RANK_NAMES[$level] ?? 'Ismeretlen';
    }

    /**
     * Kovetkezo rang szukseges pontja
     */
    public static function getNextRankPoints(int $currentLevel): ?int
    {
        $nextLevel = $currentLevel + 1;

        return self::RANK_THRESHOLDS[$nextLevel] ?? null;
    }

    /**
     * Haladas a kovetkezo rangig (0-100%)
     */
    public static function getProgressToNextRank(int $currentLevel, int $currentPoints): ?float
    {
        $nextRankPoints = self::getNextRankPoints($currentLevel);

        if ($nextRankPoints === null) {
            return null; // Mar a legmagasabb rang
        }

        $currentRankPoints = self::RANK_THRESHOLDS[$currentLevel] ?? 0;
        $pointsInCurrentRank = $currentPoints - $currentRankPoints;
        $pointsNeeded = $nextRankPoints - $currentRankPoints;

        if ($pointsNeeded <= 0) {
            return 100.0;
        }

        return min(100, ($pointsInCurrentRank / $pointsNeeded) * 100);
    }

    /**
     * Rang szint szamitas pontszam alapjan
     */
    public static function calculateRankLevel(int $points): int
    {
        $level = 1;
        foreach (self::RANK_THRESHOLDS as $rankLevel => $minPoints) {
            if ($points >= $minPoints) {
                $level = $rankLevel;
            }
        }

        return $level;
    }
}
