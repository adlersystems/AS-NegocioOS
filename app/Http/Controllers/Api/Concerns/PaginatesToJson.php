<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Pagination\AbstractPaginator;

trait PaginatesToJson
{
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
