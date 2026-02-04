<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Alap Repository implementáció
 *
 * @template TModel of Model
 * @implements BaseRepositoryContract<TModel>
 */
abstract class BaseRepository implements BaseRepositoryContract
{
    /**
     * @var class-string<TModel>
     */
    protected string $modelClass;

    /**
     * Új query builder példány
     *
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(array $filters = [], array $relations = []): Collection
    {
        $query = $this->query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator
    {
        $query = $this->query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Model
    {
        return $this->modelClass::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $filters = []): int
    {
        $query = $this->query();

        $this->applyFilters($query, $filters);

        return $query->count();
    }

    /**
     * Szűrők alkalmazása a query-re
     *
     * @param Builder<TModel> $query
     * @param array<string, mixed> $filters
     * @return void
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * Keresés alkalmazása SearchService-szel
     *
     * @param Builder<TModel> $query
     * @param string|null $search
     * @param array<string> $searchableFields
     * @return void
     */
    protected function applySearch(Builder $query, ?string $search, array $searchableFields): void
    {
        if (empty($search) || empty($searchableFields)) {
            return;
        }

        $searchService = app(\App\Services\Search\SearchService::class);
        $searchService->apply($query, $search, $searchableFields);
    }

    /**
     * Rendezés alkalmazása
     *
     * @param Builder<TModel> $query
     * @param string $field
     * @param string $direction
     * @return void
     */
    protected function applyOrdering(Builder $query, string $field, string $direction = 'asc'): void
    {
        $query->orderBy($field, $direction);
    }
}
