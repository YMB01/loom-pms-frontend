<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Lease\IndexLeaseRequest;
use App\Http\Requests\Api\Lease\StoreLeaseRequest;
use App\Http\Requests\Api\Lease\UpdateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;

class LeaseController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(IndexLeaseRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Lease::query()
            ->forCompany($companyId)
            ->with(['tenant', 'unit.property'])
            ->orderByDesc('start_date');

        if ($request->filled('status')) {
            $query->where('status', LeaseStatus::from($request->validated('status')));
        }

        if ($request->filled('property_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('property_id', $request->validated('property_id')));
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', $term))
                    ->orWhereHas('unit', fn ($u) => $u->where('unit_number', 'like', $term));
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, LeaseResource::class),
            ''
        );
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = LeaseStatus::from($data['status']);
        $data['deposit_amount'] = $data['deposit_amount'] ?? 0;

        $lease = Lease::query()->create($data);
        $lease->load(['tenant', 'unit']);

        return ApiResponse::success(
            ['lease' => LeaseResource::make($lease)->resolve()],
            'Lease created.',
            201
        );
    }

    public function show(Lease $lease): JsonResponse
    {
        $lease->load(['tenant', 'unit']);

        return ApiResponse::success(
            ['lease' => LeaseResource::make($lease)->resolve()],
            ''
        );
    }

    public function update(UpdateLeaseRequest $request, Lease $lease): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['status'])) {
            $data['status'] = LeaseStatus::from($data['status']);
        }

        $lease->update($data);
        $lease->load(['tenant', 'unit']);

        return ApiResponse::success(
            ['lease' => LeaseResource::make($lease->fresh())->resolve()],
            'Lease updated.'
        );
    }

    public function destroy(Lease $lease): JsonResponse
    {
        $lease->delete();

        return ApiResponse::success([], 'Lease deleted.');
    }
}
