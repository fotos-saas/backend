<?php

namespace App\Helpers;

class QueryHelper
{
    /**
     * Escape LIKE/ILIKE pattern special characters.
     *
     * A % és _ karakterek speciális jelentéssel bírnak az SQL LIKE operátorban:
     * - % = tetszőleges karaktersorozat
     * - _ = egyetlen karakter
     *
     * Ha a user inputként megadja ezeket, escape-elni kell,
     * különben DoS támadásra ad lehetőséget (pl. "%%%%" pattern).
     *
     * @param string $value A user input
     * @param string $escapeChar Az escape karakter (alapértelmezett: \)
     * @return string Az escape-elt string
     */
    public static function escapeLike(string $value, string $escapeChar = '\\'): string
    {
        return str_replace(
            [$escapeChar, '%', '_'],
            [$escapeChar . $escapeChar, $escapeChar . '%', $escapeChar . '_'],
            $value
        );
    }

    /**
     * Készít egy biztonságos LIKE patternt a kereséshez.
     *
     * @param string $search A keresési kifejezés
     * @param bool $wildcardStart Wildcard a string elején (%search)
     * @param bool $wildcardEnd Wildcard a string végén (search%)
     * @return string A biztonságos LIKE pattern
     */
    public static function safeLikePattern(
        string $search,
        bool $wildcardStart = true,
        bool $wildcardEnd = true
    ): string {
        $escaped = self::escapeLike($search);

        $pattern = '';
        if ($wildcardStart) {
            $pattern .= '%';
        }
        $pattern .= $escaped;
        if ($wildcardEnd) {
            $pattern .= '%';
        }

        return $pattern;
    }
}
