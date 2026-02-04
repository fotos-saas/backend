<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TabloGuestSession;
use Illuminate\Support\Collection;

/**
 * TabloGuestSession Repository Contract
 *
 * @extends BaseRepositoryContract<TabloGuestSession>
 */
interface TabloGuestSessionRepositoryContract extends BaseRepositoryContract
{
    /**
     * Session keresése token alapján
     */
    public function findByToken(string $token): ?TabloGuestSession;

    /**
     * Session keresése token és projekt alapján
     */
    public function findByTokenAndProject(string $token, int $projectId): ?TabloGuestSession;

    /**
     * Projekt összes session-je
     *
     * @param int $projectId
     * @param bool $includeBanned Tiltott session-ök is
     * @return Collection<int, TabloGuestSession>
     */
    public function getByProject(int $projectId, bool $includeBanned = true): Collection;

    /**
     * Projekt session statisztikái
     *
     * @return array{total: int, active: int, banned: int, with_selections: int}
     */
    public function getStatistics(int $projectId): array;

    /**
     * Session tiltása
     */
    public function ban(int $sessionId): bool;

    /**
     * Tiltás feloldása
     */
    public function unban(int $sessionId): bool;

    /**
     * Aktivitás frissítése
     */
    public function updateActivity(int $sessionId, ?string $ipAddress = null): bool;

    /**
     * Session keresése eszközazonosító alapján
     */
    public function findByDeviceIdentifier(string $identifier, int $projectId): ?TabloGuestSession;

    /**
     * Inaktív session-ök lekérése (pl. cleanup-hoz)
     *
     * @param int $projectId
     * @param int $inactiveMinutes
     * @return Collection<int, TabloGuestSession>
     */
    public function getInactiveSessions(int $projectId, int $inactiveMinutes = 30): Collection;
}
