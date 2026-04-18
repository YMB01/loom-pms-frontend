<?php

namespace App\Http\Requests\Api\Property;

use App\Http\Requests\Api\PaginatedIndexRequest;

class IndexPropertyRequest extends PaginatedIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
