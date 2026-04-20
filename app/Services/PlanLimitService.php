<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class PlanLimitService
{
    public function ensureCanAddProperty(Company $company): ?JsonResponse
    {
        return $this->ensureWithinLimits(
            $company,
            'properties',
            fn () => $company->properties()->count(),
            fn ($plan) => $plan->max_properties,
        );
    }

    public function ensureCanAddUnit(Company $company): ?JsonResponse
    {
        return $this->ensureWithinLimits(
            $company,
            'units',
            fn () => Unit::query()->forCompany($company->id)->count(),
            fn ($plan) => $plan->max_units,
        );
    }

    public function ensureCanAddTenant(Company $company): ?JsonResponse
    {
        return $this->ensureWithinLimits(
            $company,
            'tenants',
            fn () => $company->tenants()->count(),
            fn ($plan) => $plan->max_tenants,
        );
    }

    /**
     * @param  callable(\App\Models\Plan): int|null  $maxResolver
     * @param  callable(): int  $currentResolver
     */
    private function ensureWithinLimits(
        Company $company,
        string $resource,
        callable $currentResolver,
        callable $maxResolver,
    ): ?JsonResponse {
        if ($company->status === CompanyStatus::Suspended) {
            return ApiResponse::error(
                'This account has been suspended. Contact support if you need help.',
                403
            );
        }

        $company->loadMissing('subscription.plan');
        $subscription = $company->subscription;

        if (! $subscription) {
            return ApiResponse::error('No subscription found for this company.', 403);
        }

        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Suspended], true)) {
            return ApiResponse::error(
                'Your subscription is not active. Upgrade or renew to continue.',
                403
            );
        }

        if (SubscriptionBillingGate::requiresUpgrade($subscription)) {
            return ApiResponse::error(
                'Your trial has expired. Please subscribe to continue.',
                403,
                ['code' => 'trial_expired']
            );
        }

        $plan = $subscription->plan;
        if (! $plan) {
            return ApiResponse::error('No plan assigned to this subscription.', 403);
        }

        $max = $maxResolver($plan);
        if ($max === null) {
            return null;
        }

        $current = $currentResolver();
        if ($current >= $max) {
            $label = match ($resource) {
                'properties' => 'properties',
                'units' => 'units',
                'tenants' => 'tenants',
                default => $resource,
            };

            $msg = sprintf(
                'Upgrade your plan to add more %s. Current plan: %s. Upgrade at loompms.com/pricing',
                $label,
                $plan->name
            );

            return ApiResponse::error($msg, 403, [
                'code' => 'plan_limit_exceeded',
                'resource' => $resource,
                'limit' => $max,
                'current' => $current,
            ]);
        }

        return null;
    }
}
