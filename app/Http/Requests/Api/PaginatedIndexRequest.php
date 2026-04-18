<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;

class PaginatedIndexRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
