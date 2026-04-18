<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends CompanyScopedRequest
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
            'unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:open,in_progress,resolved'],
            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
