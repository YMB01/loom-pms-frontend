<?php

namespace App\Http\Requests\Api\Unit;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'property_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('properties', 'id')->where('company_id', $companyId),
            ],
            'unit_number' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'size_sqm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rent_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'in:available,occupied,maintenance'],
        ];
    }
}
