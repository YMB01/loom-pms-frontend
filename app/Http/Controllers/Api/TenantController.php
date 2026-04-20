<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\IndexTenantRequest;
use App\Http\Requests\Api\Tenant\StoreTenantRequest;
use App\Http\Requests\Api\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\InAppNotificationService;
use App\Services\SMSService;
use App\Enums\LeaseStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function __construct()
    {
        $this->middleware('subscription.limits:tenant')->only('store');
    }

    public function index(IndexTenantRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Tenant::query()
            ->forCompany($companyId)
            ->orderBy('name');

        if ($request->filled('property_id')) {
            $propertyId = (int) $request->validated('property_id');
            $query->whereHas('leases', function ($q) use ($propertyId): void {
                $q->whereHas('unit', fn ($u) => $u->where('property_id', $propertyId));
            });
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, TenantResource::class),
            ''
        );
    }

    public function store(StoreTenantRequest $request, SMSService $sms, InAppNotificationService $notify): JsonResponse
    {
        $companyId = $this->companyId();

        $tenant = Tenant::query()->create(array_merge(
            $request->validated(),
            ['company_id' => $companyId]
        ));

        $sms->sendWelcome($tenant);

        $notify->notifyManagers(
            $companyId,
            'New tenant onboarded',
            "{$tenant->name} was added to your portfolio.",
        );

        return ApiResponse::success(
            [
                'tenant' => TenantResource::make($tenant)->resolve(),
                'sms_queued' => filled($tenant->phone),
            ],
            'Tenant created.',
            201
        );
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return ApiResponse::success(
            ['tenant' => TenantResource::make($tenant)->resolve()],
            ''
        );
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->update($request->validated());

        return ApiResponse::success(
            ['tenant' => TenantResource::make($tenant->fresh())->resolve()],
            'Tenant updated.'
        );
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return ApiResponse::success([], 'Tenant deleted.');
    }

    /**
     * Last 6 calendar months of payments vs expected rent (active lease).
     */
    public function paymentHistoryChart(Tenant $tenant): JsonResponse
    {
        $companyId = $this->companyId();
        if ((int) $tenant->company_id !== (int) $companyId) {
            abort(404);
        }

        $now = Carbon::now();
        $lease = Lease::query()
            ->forCompany($companyId)
            ->where('tenant_id', $tenant->id)
            ->where('status', LeaseStatus::Active)
            ->orderByDesc('start_date')
            ->first();

        $expectedMonthly = $lease ? (float) $lease->rent_amount : 0.0;

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ms = $now->copy()->subMonths($i)->startOfMonth();
            $me = $now->copy()->subMonths($i)->endOfMonth();
            $paid = (float) Payment::query()
                ->forCompany($companyId)
                ->where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$ms, $me])
                ->sum('amount');
            $months[] = [
                'month_key' => $ms->format('Y-m'),
                'label' => $ms->format('M'),
                'paid' => round($paid, 2),
                'expected' => round($expectedMonthly, 2),
                'missed' => $expectedMonthly > 0 && $paid < $expectedMonthly * 0.5,
            ];
        }

        return ApiResponse::success([
            'months' => $months,
            'expected_monthly' => round($expectedMonthly, 2),
        ], '');
    }
}
