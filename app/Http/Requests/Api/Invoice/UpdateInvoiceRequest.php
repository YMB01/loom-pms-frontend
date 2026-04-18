<?php

namespace App\Http\Requests\Api\Invoice;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use App\Models\Lease;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends CompanyScopedRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $invoice = $this->route('invoice');
            $leaseId = $this->input('lease_id', $invoice?->lease_id);
            $tenantId = $this->input('tenant_id', $invoice?->tenant_id);

            $lease = Lease::query()->find($leaseId);
            if ($lease && (int) $lease->tenant_id !== (int) $tenantId) {
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
                'sometimes',
                'required',
                'integer',
                Rule::exists('leases', 'id')->whereIn('id', $leaseIds ?: [0]),
            ],
            'tenant_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'in:pending,partial,paid,overdue'],
        ];
    }
}
