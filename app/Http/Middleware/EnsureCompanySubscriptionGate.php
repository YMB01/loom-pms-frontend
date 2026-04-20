<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\User;
use App\Services\SubscriptionBillingGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySubscriptionGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if (! $request->user() instanceof User) {
            return $next($request);
        }

        if ($request->is('api/billing/*', 'api/auth/me', 'api/auth/logout')) {
            return $next($request);
        }

        $company = Company::query()
            ->with('subscription.plan')
            ->find($request->user()->company_id);

        if (! $company) {
            return $next($request);
        }

        if ($company->status === CompanyStatus::Suspended) {
            return ApiResponse::error(
                'This account has been suspended. Update billing to restore access.',
                403,
                ['code' => 'account_suspended']
            );
        }

        $subscription = $company->subscription;
        if (SubscriptionBillingGate::requiresUpgrade($subscription)) {
            return ApiResponse::error(
                'Your trial has expired. Please subscribe to continue.',
                403,
                ['code' => 'trial_expired']
            );
        }

        return $next($request);
    }
}
