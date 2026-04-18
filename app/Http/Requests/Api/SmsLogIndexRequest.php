<?php

namespace App\Http\Requests\Api;

class SmsLogIndexRequest extends PaginatedIndexRequest
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
