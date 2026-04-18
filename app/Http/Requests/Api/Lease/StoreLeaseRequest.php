<?php

namespace App\Http\Requests\Api\Lease;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use App\Models\Property;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $propertyIds = Property::query()->where('company_id', $companyId)->pluck('id')->all();

        return [
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('company_id', $companyId),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->whereIn('property_id', $propertyIds ?: [0]),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,expiring,terminated'],
        ];
    }
}
