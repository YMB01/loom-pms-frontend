<?php

namespace App\Http\Requests\Api\Tenant;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('tenants', 'email')->where('company_id', $this->user()->company_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
