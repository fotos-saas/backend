<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Központosított Google-szerű keresési service.
 *
 * Használat:
 * ```php
 * $query = app(SearchService::class)->apply($query, $search, [
 *     'columns' => ['name', 'email'],
 *     'relations' => ['school' => ['name', 'city']],
 *     'prefixes' => ['@' => ['contacts' => ['name', 'email']]],
 *     'id_column' => 'id',
 * ]);
 * ```
 */
class SearchService
{
    /**
     * Google-szerű keresés alkalmazása query-re.
     *
     * @param Builder $query Base query
     * @param string $searchTerm Felhasználói input
     * @param array $config Konfiguráció:
     *   - columns: ['name', 'email'] - Saját oszlopok
     *   - relations: ['school' => ['name', 'city']] - Relációs oszlopok
     *   - prefixes: ['@' => ['contacts' => ['name', 'email']]] - Prefix mapping
     *   - id_column: 'id' - ID oszlop neve (alapértelmezett: 'id')
     * @return Builder
     */
    public function apply(Builder $query, string $searchTerm, array $config): Builder
    {
        $searchTerm = trim($searchTerm);
        if (empty($searchTerm)) {
            return $query;
        }

        $parsed = SearchParser::parse($searchTerm);

        // ID keresés (#123)
        if ($parsed->hasIdSearch()) {
            $idColumn = $config['id_column'] ?? 'id';
            return $query->where($idColumn, $parsed->getId());
        }

        // Prefix keresés (@név)
        if ($parsed->hasPrefix()) {
            return $this->applyPrefixSearch($query, $parsed, $config);
        }

        // Általános keresés (idézőjeles + szabad szavak)
        return $this->applyTermSearch($query, $parsed->getAllTerms(), $config);
    }

    /**
     * Prefix-alapú keresés (@ügyintéző).
     */
    protected function applyPrefixSearch(Builder $query, SearchParser $parsed, array $config): Builder
    {
        $prefix = $parsed->getPrefix();
        $value = $parsed->getPrefixValue();
        $prefixConfig = $config['prefixes'][$prefix] ?? null;

        if (!$prefixConfig) {
            // Fallback általános keresésre ha a prefix nincs konfigurálva
            return $this->applyTermSearch($query, [$value], $config);
        }

        // Prefix-hez tartozó relációkban keresés
        return $query->where(function ($q) use ($prefixConfig, $value) {
            foreach ($prefixConfig as $relation => $columns) {
                $q->orWhereHas($relation, function ($subQ) use ($columns, $value) {
                    $subQ->where(function ($nested) use ($columns, $value) {
                        foreach ($columns as $col) {
                            $this->addUnaccentCondition($nested, $col, $value, 'or');
                        }
                    });
                });
            }
        });
    }

    /**
     * Term-alapú keresés.
     * Minden term-nek AND kapcsolatban kell teljesülnie,
     * de egy term-en belül az oszlopok OR kapcsolatban vannak.
     */
    protected function applyTermSearch(Builder $query, array $terms, array $config): Builder
    {
        $columns = $config['columns'] ?? [];
        $relations = $config['relations'] ?? [];

        foreach ($terms as $term) {
            $query->where(function ($q) use ($columns, $relations, $term) {
                // Saját oszlopok
                foreach ($columns as $column) {
                    $this->addUnaccentCondition($q, $column, $term, 'or');
                }

                // Relációk
                foreach ($relations as $relation => $relColumns) {
                    $q->orWhereHas($relation, function ($subQ) use ($relColumns, $term) {
                        $subQ->where(function ($nested) use ($relColumns, $term) {
                            foreach ($relColumns as $col) {
                                $this->addUnaccentCondition($nested, $col, $term, 'or');
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Engedélyezett oszlopnevek a kereséshez (SQL injection védelem).
     * Csak ezek az oszlopok használhatók a raw query-kben.
     */
    private const ALLOWED_COLUMNS = [
        'name', 'email', 'phone', 'city', 'address', 'title', 'description',
        'school_name', 'class_name', 'first_name', 'last_name', 'full_name',
        'company_name', 'contact_name', 'note', 'notes', 'comment', 'body',
        'canonical_name', 'title_prefix', 'position', 'alias_name', 'file_name',
    ];

    /**
     * PostgreSQL unaccent + ILIKE feltétel hozzáadása.
     * Ékezet-független keresést biztosít.
     */
    protected function addUnaccentCondition(Builder $query, string $column, string $value, string $boolean = 'and'): void
    {
        // SQL injection védelem: csak engedélyezett oszlopnevek
        // Az oszlopnév lehet "table.column" formátumú is
        $columnName = str_contains($column, '.') ? explode('.', $column)[1] : $column;
        if (!in_array($columnName, self::ALLOWED_COLUMNS, true)) {
            throw new \InvalidArgumentException("Invalid column name for search: {$column}");
        }

        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $query->$method("unaccent({$column}) ILIKE unaccent(?)", ["%{$value}%"]);
    }
}
