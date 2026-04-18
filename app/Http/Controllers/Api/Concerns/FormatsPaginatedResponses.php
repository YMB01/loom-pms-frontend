<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

trait FormatsPaginatedResponses
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{items: mixed, meta: array<string, mixed>}
     */
    protected function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'items' => $resourceClass::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
