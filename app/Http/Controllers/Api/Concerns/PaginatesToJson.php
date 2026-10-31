<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;

trait PaginatesToJson
{
    /**
     * Smallest accepted page size.
     */
    private const MIN_ITEMS = 1;

    /**
     * Largest accepted page size for list and nested responses.
     */
    private const MAX_ITEMS = 100;

    /**
     * Resolve a page size from the request, clamped into the supported range.
     */
    private function pageSize(Request $request, string $key, int $default): int
    {
        return max(self::MIN_ITEMS, min($request->integer($key, $default), self::MAX_ITEMS));
    }

    /**
     * Shared pagination metadata for API list responses.
     *
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    private function meta(AbstractPaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
