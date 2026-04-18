<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'tenant_id' => $this->tenant_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'invoice' => InvoiceResource::make($this->whenLoaded('invoice')),
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
