<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property' => PropertyResource::make($this->whenLoaded('property')),
            'unit_number' => $this->unit_number,
            'type' => $this->type,
            'floor' => $this->floor,
            'size_sqm' => $this->size_sqm,
            'rent_amount' => $this->rent_amount,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
