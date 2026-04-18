<?php

namespace App\Http\Requests\Api\Invoice;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;

class GenerateMonthlyInvoicesRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
