<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Company account metadata only — no tenant, lease, or invoice payloads.
 *
 * @mixin \App\Models\Company
 */
class SuperAdminCompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'plan' => $this->subscription?->plan?->slug ?? 'free',
            'plan_id' => $this->subscription?->plan_id,
            'subscription_status' => $this->subscription?->status?->value ?? 'active',
            'account_status' => $this->status?->value ?? 'active',
            'trial_ends_at' => $this->subscription?->trial_ends_at?->toIso8601String(),
            'signup_at' => $this->created_at?->toIso8601String(),
            'properties_count' => (int) ($this->properties_count ?? 0),
            'units_count' => (int) ($this->units_count ?? 0),
            'tenants_count' => (int) ($this->tenants_count ?? 0),
            'last_login_at' => $this->users_max_last_login_at
                ? Carbon::parse($this->users_max_last_login_at)->toIso8601String()
                : null,
        ];
    }
}
