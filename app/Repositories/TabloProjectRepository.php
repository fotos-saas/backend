<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TabloProject;
use App\Repositories\Contracts\TabloProjectRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * TabloProject Repository implementáció
 *
 * @extends BaseRepository<TabloProject>
 */
class TabloProjectRepository extends BaseRepository implements TabloProjectRepositoryContract
{
    protected string $modelClass = TabloProject::class;

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?TabloProject
    {
        return $this->query()->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByExternalId(string $externalId): ?TabloProject
    {
        return $this->query()
            ->where('fotocms_id', $externalId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findForPartner(int $projectId, int $partnerId): ?TabloProject
    {
        return $this->query()
            ->where('id', $projectId)
            ->where('partner_id', $partnerId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByPartner(
        int $partnerId,
        int $perPage = 15,
        ?string $search = null,
        array $relations = []
    ): LengthAwarePaginator {
        $query = $this->query()
            ->where('partner_id', $partnerId);

        if (! empty($relations)) {
            $query->with($relations);
        }

        if ($search) {
            $this->applySearch($query, $search, ['name', 'class_name', 'class_year']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function countByPartner(int $partnerId): int
    {
        return $this->query()
            ->where('partner_id', $partnerId)
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getWithStats(int $partnerId, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->query()
            ->where('partner_id', $partnerId)
            ->with(['school', 'contacts'])
            ->withCount(['guestSessions', 'persons']);

        if ($search) {
            $this->applySearch($query, $search, ['name', 'class_name', 'class_year']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getWithPersons(int $projectId): ?TabloProject
    {
        return $this->query()
            ->with('persons')
            ->find($projectId);
    }

    /**
     * @deprecated Use getWithPersons() instead
     */
    public function getWithMissingPersons(int $projectId): ?TabloProject
    {
        return $this->getWithPersons($projectId);
    }

    /**
     * {@inheritdoc}
     */
    public function getSamples(int $projectId): Collection
    {
        $project = $this->findById($projectId);

        if (! $project) {
            return collect();
        }

        return $project->getMedia('samples');
    }

    /**
     * {@inheritdoc}
     */
    public function countByStatus(int $partnerId): array
    {
        return $this->query()
            ->where('partner_id', $partnerId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getUpcomingPhotoshoots(int $partnerId, int $limit = 5): Collection
    {
        return $this->query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('photo_date')
            ->where('photo_date', '>=', now())
            ->orderBy('photo_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): TabloProject
    {
        return TabloProject::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $data): bool
    {
        /** @var TabloProject $model */
        return $model->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function attachContact(int $projectId, int $contactId, bool $isPrimary = false): void
    {
        $project = $this->findById($projectId);

        if (! $project) {
            return;
        }

        $project->contacts()->attach($contactId, ['is_primary' => $isPrimary]);
    }

    /**
     * {@inheritdoc}
     */
    public function detachContact(int $projectId, int $contactId): void
    {
        $project = $this->findById($projectId);

        if (! $project) {
            return;
        }

        $project->contacts()->detach($contactId);
    }

    /**
     * {@inheritdoc}
     */
    public function setPrimaryContact(int $projectId, int $contactId): void
    {
        $project = $this->findById($projectId);

        if (! $project) {
            return;
        }

        // Minden kontakt is_primary = false
        $project->contacts()->updateExistingPivot(
            $project->contacts()->pluck('tablo_contacts.id')->toArray(),
            ['is_primary' => false]
        );

        // A megadott kontakt is_primary = true
        $project->contacts()->updateExistingPivot($contactId, ['is_primary' => true]);
    }
}
