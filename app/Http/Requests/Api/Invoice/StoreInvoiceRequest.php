<?php

namespace App\Http\Requests\Api\Invoice;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use App\Models\Lease;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends CompanyScopedRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lease = Lease::query()->find($this->input('lease_id'));
            if ($lease && (int) $lease->tenant_id !== (int) $this->input('tenant_id')) {
                $validator->errors()->add('tenant_id', 'The tenant must match the selected lease.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $leaseIds = Lease::query()->forCompany($companyId)->pluck('id')->all();

        return [
            'lease_id' => [
                'required',
                'integer',
                Rule::exists('leases', 'id')->whereIn('id', $leaseIds ?: [0]),
            ],
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['sometimes', 'in:pending,partial,paid,overdue'],
        ];
    }
}
