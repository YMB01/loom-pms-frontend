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
use App\Models\Tenant;
use App\Services\SMSService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

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

    public function store(StoreTenantRequest $request, SMSService $sms): JsonResponse
    {
        $companyId = $this->companyId();

        $tenant = Tenant::query()->create(array_merge(
            $request->validated(),
            ['company_id' => $companyId]
        ));

        $sms->sendWelcome($tenant);

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
}
