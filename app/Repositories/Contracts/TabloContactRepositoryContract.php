<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TabloContact;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * TabloContact Repository Contract
 *
 * @extends BaseRepositoryContract<TabloContact>
 */
interface TabloContactRepositoryContract extends BaseRepositoryContract
{
    /**
     * Kontakt keresése partner-scoped ID alapján
     * Biztosítja, hogy a kontakt a megadott partnerhez tartozik
     */
    public function findForPartner(int $contactId, int $partnerId): ?TabloContact;

    /**
     * Partner kontaktjainak lekérése lapozással
     *
     * @param int $partnerId
     * @param int $perPage
     * @param string|null $search Keresési kifejezés
     * @param array<string> $relations Betöltendő relációk
     * @return LengthAwarePaginator<TabloContact>
     */
    public function getByPartner(
        int $partnerId,
        int $perPage = 15,
        ?string $search = null,
        array $relations = []
    ): LengthAwarePaginator;

    /**
     * Partner kontaktjainak száma
     */
    public function countByPartner(int $partnerId): int;

    /**
     * Partner összes kontaktja (lapozás nélkül, pl. dropdown-hoz)
     *
     * @return Collection<int, TabloContact>
     */
    public function getAllForPartner(int $partnerId): Collection;

    /**
     * Kontakt keresése email alapján (partner-scoped)
     */
    public function findByEmail(string $email, int $partnerId): ?TabloContact;

    /**
     * Projektek hozzárendelése kontakthoz (append)
     *
     * @param int $contactId
     * @param array<int> $projectIds
     * @param bool $isPrimary
     */
    public function attachProjects(int $contactId, array $projectIds, bool $isPrimary = false): void;

    /**
     * Projektek szinkronizálása kontakthoz (replace)
     * Megőrzi a meglévő is_primary értékeket
     *
     * @param int $contactId
     * @param array<int> $projectIds
     */
    public function syncProjects(int $contactId, array $projectIds): void;

    /**
     * Kontakt leválasztása projektről
     */
    public function detachProject(int $contactId, int $projectId): void;

    /**
     * Projekt kontaktjainak lekérése
     *
     * @return Collection<int, TabloContact>
     */
    public function getByProject(int $projectId): Collection;
}
