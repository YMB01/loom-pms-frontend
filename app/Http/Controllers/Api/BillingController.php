<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Plan;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use InteractsWithCompany;

    public function options(StripeBillingService $stripe): JsonResponse
    {
        $plans = Plan::query()
            ->whereIn('slug', ['basic', 'pro'])
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn (Plan $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'price' => (string) $p->price,
                'max_properties' => $p->max_properties,
                'max_units' => $p->max_units,
                'max_tenants' => $p->max_tenants,
            ]);

        return ApiResponse::success([
            'stripe_configured' => $stripe->isConfigured(),
            'plans' => $plans,
        ], '');
    }

    public function checkout(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'in:basic,pro'],
        ]);

        try {
            $company = Company::query()->findOrFail($this->companyId());
            $url = $stripe->createCheckoutSession($company, $data['plan_slug']);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error($e->getMessage(), 503);
        }

        return ApiResponse::success([
            'url' => $url,
        ], 'Redirect to Stripe Checkout.');
    }

    public function portal(StripeBillingService $stripe): JsonResponse
    {
        try {
            $company = Company::query()->findOrFail($this->companyId());
            if (! $company->stripe_customer_id) {
                return ApiResponse::error('No billing account yet. Subscribe to a paid plan first.', 422);
            }

            $url = $stripe->createBillingPortalSession($company);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error($e->getMessage(), 503);
        }

        return ApiResponse::success([
            'url' => $url,
        ], '');
    }
}
