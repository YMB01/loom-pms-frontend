<?php

namespace App\Http\Requests\Api\Tenant;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('tenants', 'email')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->route('tenant')),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
