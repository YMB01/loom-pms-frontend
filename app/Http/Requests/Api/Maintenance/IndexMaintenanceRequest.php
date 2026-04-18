<?php

namespace App\Http\Requests\Api\Maintenance;

use App\Http\Requests\Api\PaginatedIndexRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceRequest extends PaginatedIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'property_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where('company_id', $this->user()->company_id),
            ],
            'status' => ['sometimes', 'nullable', 'in:open,in_progress,resolved'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
