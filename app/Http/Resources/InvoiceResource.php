<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'tenant_id' => $this->tenant_id,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status->value,
            'lease' => LeaseResource::make($this->whenLoaded('lease')),
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
