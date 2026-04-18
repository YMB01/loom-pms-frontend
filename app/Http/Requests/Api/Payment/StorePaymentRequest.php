<?php

namespace App\Http\Requests\Api\Payment;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends CompanyScopedRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $invoice = Invoice::query()->find($this->input('invoice_id'));
            if ($invoice && (int) $invoice->tenant_id !== (int) $this->input('tenant_id')) {
                $validator->errors()->add('tenant_id', 'The tenant must match the invoice.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $invoiceIds = Invoice::query()->forCompany($companyId)->pluck('id')->all();

        return [
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->whereIn('id', $invoiceIds ?: [0]),
            ],
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
