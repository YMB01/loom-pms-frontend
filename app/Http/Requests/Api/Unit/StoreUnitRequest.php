<?php

namespace App\Http\Requests\Api\Unit;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where('company_id', $companyId),
            ],
            'unit_number' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,occupied,maintenance'],
        ];
    }
}
