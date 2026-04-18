<?php

namespace App\Http\Requests\Api\Property;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;

class StorePropertyRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'total_units' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
