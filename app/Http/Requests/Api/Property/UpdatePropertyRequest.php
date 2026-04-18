<?php

namespace App\Http\Requests\Api\Property;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;

class UpdatePropertyRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_units' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
