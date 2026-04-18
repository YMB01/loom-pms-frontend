<?php

namespace App\Http\Requests\Api\Payment;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentRequest extends CompanyScopedRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $payment = $this->route('payment');
            $invoiceId = $this->input('invoice_id', $payment->invoice_id);
            $tenantId = $this->input('tenant_id', $payment->tenant_id);

            $invoice = Invoice::query()->find($invoiceId);
            if ($invoice && (int) $invoice->tenant_id !== (int) $tenantId) {
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
                'sometimes',
                'required',
                'integer',
                Rule::exists('invoices', 'id')->whereIn('id', $invoiceIds ?: [0]),
            ],
            'tenant_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'method' => ['sometimes', 'required', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
