<?php

namespace App\Http\Requests\Api\Lease;

use App\Http\Requests\Api\PaginatedIndexRequest;
use Illuminate\Validation\Rule;

class IndexLeaseRequest extends PaginatedIndexRequest
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
            'status' => ['sometimes', 'nullable', 'in:active,expiring,terminated'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
