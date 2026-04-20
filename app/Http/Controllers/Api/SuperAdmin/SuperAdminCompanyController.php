<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateCompanySubscriptionRequest;
use App\Http\Resources\SuperAdminCompanyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $companies = Company::query()
            ->with(['subscription.plan'])
            ->withCount(['properties', 'units', 'tenants'])
            ->withMax('users', 'last_login_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success([
            'companies' => SuperAdminCompanyResource::collection($companies->items()),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ], '');
    }

    public function activate(Company $company): JsonResponse
    {
        $company->update([
            'status' => CompanyStatus::Active,
        ]);

        if ($sub = $company->subscription) {
            if ($sub->status !== SubscriptionStatus::Cancelled) {
                $sub->update(['status' => SubscriptionStatus::Active]);
            }
        }

        $fresh = Company::query()
            ->with(['subscription.plan'])
            ->withCount(['properties', 'units', 'tenants'])
            ->withMax('users', 'last_login_at')
            ->findOrFail($company->id);

        return ApiResponse::success([
            'company' => SuperAdminCompanyResource::make($fresh)->resolve(),
        ], 'Company activated.');
    }

    public function suspend(Company $company): JsonResponse
    {
        $company->update([
            'status' => CompanyStatus::Suspended,
        ]);

        $company->subscription?->update([
            'status' => SubscriptionStatus::Suspended,
        ]);

        $fresh = Company::query()
            ->with(['subscription.plan'])
            ->withCount(['properties', 'units', 'tenants'])
            ->withMax('users', 'last_login_at')
            ->findOrFail($company->id);

        return ApiResponse::success([
            'company' => SuperAdminCompanyResource::make($fresh)->resolve(),
        ], 'Company suspended.');
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return ApiResponse::success([], 'Company deleted.');
    }

    public function updateSubscription(UpdateCompanySubscriptionRequest $request, Company $company): JsonResponse
    {
        $planId = (int) $request->validated('plan_id');

        Subscription::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'plan_id' => $planId,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]
        );

        $fresh = Company::query()
            ->with(['subscription.plan'])
            ->withCount(['properties', 'units', 'tenants'])
            ->withMax('users', 'last_login_at')
            ->findOrFail($company->id);

        return ApiResponse::success([
            'company' => SuperAdminCompanyResource::make($fresh)->resolve(),
        ], 'Subscription updated.');
    }
}
