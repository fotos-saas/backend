<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Common pagination logic for API controllers.
 *
 * Centralizes per_page parameter handling with configurable defaults and max limits.
 * Prevents abuse by capping the max per_page value.
 */
trait HasPagination
{
    /**
     * Get sanitized pagination parameters from request.
     *
     * @param Request $request The HTTP request
     * @param int $default Default items per page (default: 15)
     * @param int $max Maximum allowed items per page (default: 50)
     * @return int Sanitized per_page value
     */
    protected function getPerPage(Request $request, int $default = 15, int $max = 50): int
    {
        return min(max((int) $request->input('per_page', $default), 1), $max);
    }

    /**
     * Get sorting parameters from request.
     *
     * @param Request $request The HTTP request
     * @param string $defaultSort Default sort column
     * @param string $defaultDirection Default sort direction
     * @param array $allowedColumns Allowed sort columns (whitelist)
     * @return array{sort: string, direction: string}
     */
    protected function getSortParams(
        Request $request,
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
        array $allowedColumns = []
    ): array {
        $sort = $request->input('sort_by', $defaultSort);
        $direction = strtolower($request->input('sort_dir', $defaultDirection));

        // Whitelist sort columns to prevent SQL injection
        if (!empty($allowedColumns) && !in_array($sort, $allowedColumns, true)) {
            $sort = $defaultSort;
        }

        // Validate direction
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return ['sort' => $sort, 'direction' => $direction];
    }
}
