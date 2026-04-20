<?php

namespace App\Http\Resources;

use App\Services\SubscriptionBillingGate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'billing_portal_available' => filled($this->stripe_customer_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'requires_upgrade' => $this->relationLoaded('subscription') && $this->subscription
                ? SubscriptionBillingGate::requiresUpgrade($this->subscription)
                : false,
            'subscription' => $this->when(
                $this->relationLoaded('subscription') && $this->subscription,
                function () {
                    $sub = $this->subscription;

                    return [
                        'status' => $sub->status->value,
                        'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
                        'plan' => $sub->relationLoaded('plan') && $sub->plan
                            ? [
                                'slug' => $sub->plan->slug,
                                'name' => $sub->plan->name,
                                'price' => (string) $sub->plan->price,
                            ]
                            : null,
                    ];
                }
            ),
        ];
    }
}
