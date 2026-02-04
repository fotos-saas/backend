<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TabloGuestSession;
use App\Repositories\Contracts\TabloGuestSessionRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * TabloGuestSession Repository implementáció
 *
 * @extends BaseRepository<TabloGuestSession>
 */
class TabloGuestSessionRepository extends BaseRepository implements TabloGuestSessionRepositoryContract
{
    protected string $modelClass = TabloGuestSession::class;

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?TabloGuestSession
    {
        return $this->query()->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByToken(string $token): ?TabloGuestSession
    {
        return $this->query()
            ->where('session_token', $token)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByTokenAndProject(string $token, int $projectId): ?TabloGuestSession
    {
        return $this->query()
            ->where('session_token', $token)
            ->where('tablo_project_id', $projectId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByProject(int $projectId, bool $includeBanned = true): Collection
    {
        $query = $this->query()
            ->where('tablo_project_id', $projectId);

        if (! $includeBanned) {
            $query->where('is_banned', false);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(int $projectId): array
    {
        $sessions = $this->query()
            ->where('tablo_project_id', $projectId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_banned = false THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_banned = true THEN 1 ELSE 0 END) as banned,
                SUM(CASE WHEN tablo_missing_person_id IS NOT NULL THEN 1 ELSE 0 END) as with_selections
            ')
            ->first();

        return [
            'total' => (int) ($sessions->total ?? 0),
            'active' => (int) ($sessions->active ?? 0),
            'banned' => (int) ($sessions->banned ?? 0),
            'with_selections' => (int) ($sessions->with_selections ?? 0),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): TabloGuestSession
    {
        return TabloGuestSession::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function ban(int $sessionId): bool
    {
        $session = $this->findById($sessionId);

        if (! $session) {
            return false;
        }

        return $session->update(['is_banned' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function unban(int $sessionId): bool
    {
        $session = $this->findById($sessionId);

        if (! $session) {
            return false;
        }

        return $session->update(['is_banned' => false]);
    }

    /**
     * {@inheritdoc}
     */
    public function updateActivity(int $sessionId, ?string $ipAddress = null): bool
    {
        $session = $this->findById($sessionId);

        if (! $session) {
            return false;
        }

        $data = ['last_activity_at' => now()];

        if ($ipAddress !== null) {
            $data['ip_address'] = $ipAddress;
        }

        return $session->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function findByDeviceIdentifier(string $identifier, int $projectId): ?TabloGuestSession
    {
        return $this->query()
            ->where('device_identifier', $identifier)
            ->where('tablo_project_id', $projectId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getInactiveSessions(int $projectId, int $inactiveMinutes = 30): Collection
    {
        return $this->query()
            ->where('tablo_project_id', $projectId)
            ->where('is_banned', false)
            ->where(function ($query) use ($inactiveMinutes) {
                $query->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', now()->subMinutes($inactiveMinutes));
            })
            ->get();
    }
}
