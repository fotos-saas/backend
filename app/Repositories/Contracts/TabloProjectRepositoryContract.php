<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TabloProject;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * TabloProject Repository Contract
 *
 * @extends BaseRepositoryContract<TabloProject>
 */
interface TabloProjectRepositoryContract extends BaseRepositoryContract
{
    /**
     * Projekt keresése külső ID alapján (fotocms_id)
     */
    public function findByExternalId(string $externalId): ?TabloProject;

    /**
     * Projekt keresése partner-scoped ID alapján
     * Biztosítja, hogy a projekt a megadott partnerhez tartozik
     */
    public function findForPartner(int $projectId, int $partnerId): ?TabloProject;

    /**
     * Partner projektjeinek lekérése lapozással
     *
     * @param int $partnerId
     * @param int $perPage
     * @param string|null $search Keresési kifejezés
     * @param array<string> $relations Betöltendő relációk
     * @return LengthAwarePaginator<TabloProject>
     */
    public function getByPartner(
        int $partnerId,
        int $perPage = 15,
        ?string $search = null,
        array $relations = []
    ): LengthAwarePaginator;

    /**
     * Partner projektjeinek száma
     */
    public function countByPartner(int $partnerId): int;

    /**
     * Projektek statisztikákkal (dashboard)
     *
     * @param int $partnerId
     * @param int $perPage
     * @param string|null $search
     * @return LengthAwarePaginator<TabloProject>
     */
    public function getWithStats(int $partnerId, int $perPage = 15, ?string $search = null): LengthAwarePaginator;

    /**
     * Projekt hiányzó személyeivel
     */
    public function getWithMissingPersons(int $projectId): ?TabloProject;

    /**
     * Projekt mintaképeinek lekérése
     *
     * @return Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media>
     */
    public function getSamples(int $projectId): Collection;

    /**
     * Projekt státusz szerinti számolás
     *
     * @return array<string, int>
     */
    public function countByStatus(int $partnerId): array;

    /**
     * Közelgő fotózások
     *
     * @param int $partnerId
     * @param int $limit
     * @return Collection<int, TabloProject>
     */
    public function getUpcomingPhotoshoots(int $partnerId, int $limit = 5): Collection;

    /**
     * Kontakt hozzárendelése projekthez
     */
    public function attachContact(int $projectId, int $contactId, bool $isPrimary = false): void;

    /**
     * Kontakt leválasztása projektről
     */
    public function detachContact(int $projectId, int $contactId): void;

    /**
     * Elsődleges kontakt beállítása (többi is_primary = false)
     */
    public function setPrimaryContact(int $projectId, int $contactId): void;
}
