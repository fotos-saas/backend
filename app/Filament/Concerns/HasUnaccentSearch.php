<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait az ékezet- és kis/nagybetű-független kereséshez Filament táblákban.
 *
 * Használat:
 * 1. Add hozzá a trait-et a Resource-hoz: use HasUnaccentSearch;
 * 2. Definiáld a kereshető oszlopokat a Resource-ban: protected static array $unaccentSearchColumns = ['name', 'school.name'];
 * 3. A table() metódusban: ->searchable()->searchUsing(static::getUnaccentSearchCallback())
 *
 * PostgreSQL unaccent extension szükséges:
 * CREATE EXTENSION IF NOT EXISTS unaccent;
 */
trait HasUnaccentSearch
{
    /**
     * Visszaadja a kereshető oszlopok listáját.
     * A Resource-ban definiáld: protected static array $unaccentSearchColumns = [...];
     */
    protected static function getUnaccentSearchColumns(): array
    {
        return static::$unaccentSearchColumns ?? [];
    }

    /**
     * Ékezet- és kis/nagybetű-független keresés alkalmazása.
     */
    public static function applyUnaccentSearch(Builder $query, string $search): Builder
    {
        $columns = static::getUnaccentSearchColumns();

        if (empty($columns)) {
            return $query;
        }

        $search = trim($search);
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($columns, $search) {
            foreach ($columns as $column) {
                // Kapcsolat oszlop kezelése (pl. 'school.name')
                if (str_contains($column, '.')) {
                    [$relation, $relationColumn] = explode('.', $column, 2);
                    $query->orWhereHas($relation, function (Builder $relationQuery) use ($relationColumn, $search) {
                        static::applyUnaccentCondition($relationQuery, $relationColumn, $search);
                    });
                } else {
                    static::applyUnaccentCondition($query, $column, $search, 'or');
                }
            }
        });
    }

    /**
     * Egyetlen oszlopra alkalmazza az unaccent keresést.
     */
    protected static function applyUnaccentCondition(
        Builder $query,
        string $column,
        string $search,
        string $boolean = 'and'
    ): void {
        // PostgreSQL unaccent + ILIKE kombináció
        // ILIKE = kis/nagybetű független
        // unaccent() = ékezet független
        $query->whereRaw(
            "unaccent(LOWER({$column})) ILIKE unaccent(LOWER(?))",
            ["%{$search}%"],
            $boolean
        );
    }

    /**
     * Helper a tábla keresési callback-jéhez.
     * Használat: ->searchable()->searchUsing(static::getUnaccentSearchCallback())
     */
    public static function getUnaccentSearchCallback(): \Closure
    {
        return fn (Builder $query, string $search): Builder => static::applyUnaccentSearch($query, $search);
    }
}
