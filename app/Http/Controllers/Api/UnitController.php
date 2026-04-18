<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Unit\IndexUnitRequest;
use App\Http\Requests\Api\Unit\StoreUnitRequest;
use App\Http\Requests\Api\Unit\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(IndexUnitRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Unit::query()->forCompany($companyId)->orderBy('unit_number');

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->validated('property_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', UnitStatus::from($request->validated('status')));
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where('unit_number', 'like', $term);
        }

        $paginator = $query->with('property')->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, UnitResource::class),
            ''
        );
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = UnitStatus::from($data['status']);

        $unit = Unit::query()->create($data);

        return ApiResponse::success(
            ['unit' => UnitResource::make($unit)->resolve()],
            'Unit created.',
            201
        );
    }

    public function show(Unit $unit): JsonResponse
    {
        return ApiResponse::success(
            ['unit' => UnitResource::make($unit)->resolve()],
            ''
        );
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['status'])) {
            $data['status'] = UnitStatus::from($data['status']);
        }

        $unit->update($data);

        return ApiResponse::success(
            ['unit' => UnitResource::make($unit->fresh())->resolve()],
            'Unit updated.'
        );
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return ApiResponse::success([], 'Unit deleted.');
    }
}
