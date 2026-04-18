<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'unit_id' => $this->unit_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'status' => $this->status->value,
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'unit' => UnitResource::make($this->whenLoaded('unit')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
