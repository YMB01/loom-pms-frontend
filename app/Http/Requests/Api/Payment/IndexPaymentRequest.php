<?php

namespace App\Http\Requests\Api\Payment;

use App\Http\Requests\Api\PaginatedIndexRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequest extends PaginatedIndexRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
