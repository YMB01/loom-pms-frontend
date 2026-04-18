<?php

namespace App\Http\Requests\Api\Invoice;

use App\Http\Requests\Api\PaginatedIndexRequest;
use Illuminate\Validation\Rule;

class IndexInvoiceRequest extends PaginatedIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', 'in:pending,partial,paid,overdue'],
            'property_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where('company_id', $this->user()->company_id),
            ],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
