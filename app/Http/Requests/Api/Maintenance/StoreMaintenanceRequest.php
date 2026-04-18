<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends CompanyScopedRequest
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
            'unit' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:open,in_progress,resolved'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
