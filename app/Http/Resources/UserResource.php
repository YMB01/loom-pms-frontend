<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'phone' => $this->phone,
            'company' => $this->when(
                $this->relationLoaded('company'),
                fn () => $this->company
                    ? CompanyResource::make($this->company)->resolve()
                    : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
