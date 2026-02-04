<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Alap Repository Contract - közös metódusok definíciója
 *
 * @template TModel of Model
 */
interface BaseRepositoryContract
{
    /**
     * Rekord keresése ID alapján
     *
     * @param int $id
     * @return TModel|null
     */
    public function findById(int $id): ?Model;

    /**
     * Összes rekord lekérése
     *
     * @param array<string, mixed> $filters Szűrési feltételek
     * @param array<string> $relations Betöltendő relációk
     * @return Collection<int, TModel>
     */
    public function getAll(array $filters = [], array $relations = []): Collection;

    /**
     * Rekordok lapozással
     *
     * @param int $perPage Elemek száma oldalanként
     * @param array<string, mixed> $filters Szűrési feltételek
     * @param array<string> $relations Betöltendő relációk
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator;

    /**
     * Új rekord létrehozása
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * Rekord frissítése
     *
     * @param TModel $model
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(Model $model, array $data): bool;

    /**
     * Rekord törlése
     *
     * @param TModel $model
     * @return bool
     */
    public function delete(Model $model): bool;

    /**
     * Rekordok számolása
     *
     * @param array<string, mixed> $filters Szűrési feltételek
     * @return int
     */
    public function count(array $filters = []): int;
}
