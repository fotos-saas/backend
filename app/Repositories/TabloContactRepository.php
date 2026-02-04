<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TabloContact;
use App\Repositories\Contracts\TabloContactRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * TabloContact Repository implementáció
 *
 * @extends BaseRepository<TabloContact>
 */
class TabloContactRepository extends BaseRepository implements TabloContactRepositoryContract
{
    protected string $modelClass = TabloContact::class;

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?TabloContact
    {
        return $this->query()->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findForPartner(int $contactId, int $partnerId): ?TabloContact
    {
        return $this->query()
            ->where('id', $contactId)
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
            $this->applySearch($query, $search, ['name', 'email', 'phone']);
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
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
    public function getAllForPartner(int $partnerId): Collection
    {
        return $this->query()
            ->where('partner_id', $partnerId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email, int $partnerId): ?TabloContact
    {
        return $this->query()
            ->where('email', $email)
            ->where('partner_id', $partnerId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): TabloContact
    {
        return TabloContact::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $data): bool
    {
        /** @var TabloContact $model */
        return $model->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function attachProjects(int $contactId, array $projectIds, bool $isPrimary = false): void
    {
        $contact = $this->findById($contactId);

        if (! $contact || empty($projectIds)) {
            return;
        }

        $pivotData = [];
        foreach ($projectIds as $projectId) {
            $pivotData[$projectId] = ['is_primary' => $isPrimary];
        }

        $contact->projects()->attach($pivotData);
    }

    /**
     * {@inheritdoc}
     */
    public function syncProjects(int $contactId, array $projectIds): void
    {
        $contact = $this->findById($contactId);

        if (! $contact) {
            return;
        }

        // Meglévő is_primary értékek megőrzése
        $existingPivots = $contact->projects()
            ->pluck('tablo_project_contacts.is_primary', 'tablo_projects.id')
            ->toArray();

        $pivotData = [];
        foreach ($projectIds as $projectId) {
            // Ha már létezett, megőrizzük az is_primary értéket
            $pivotData[$projectId] = [
                'is_primary' => $existingPivots[$projectId] ?? false,
            ];
        }

        $contact->projects()->sync($pivotData);
    }

    /**
     * {@inheritdoc}
     */
    public function detachProject(int $contactId, int $projectId): void
    {
        $contact = $this->findById($contactId);

        if (! $contact) {
            return;
        }

        $contact->projects()->detach($projectId);
    }

    /**
     * {@inheritdoc}
     */
    public function getByProject(int $projectId): Collection
    {
        return $this->query()
            ->whereHas('projects', function ($query) use ($projectId) {
                $query->where('tablo_projects.id', $projectId);
            })
            ->get();
    }
}
