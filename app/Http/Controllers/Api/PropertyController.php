<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Property\IndexPropertyRequest;
use App\Http\Requests\Api\Property\StorePropertyRequest;
use App\Http\Requests\Api\Property\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function __construct()
    {
        $this->middleware('subscription.limits:property')->only('store');
    }

    public function index(IndexPropertyRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Property::query()
            ->forCompany($companyId)
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, PropertyResource::class),
            ''
        );
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $companyId = $this->companyId();

        $property = Property::query()->create(array_merge(
            $request->validated(),
            ['company_id' => $companyId]
        ));

        return ApiResponse::success(
            ['property' => PropertyResource::make($property)->resolve()],
            'Property created.',
            201
        );
    }

    public function show(Property $property): JsonResponse
    {
        return ApiResponse::success(
            ['property' => PropertyResource::make($property)->resolve()],
            ''
        );
    }

    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property->update($request->validated());

        return ApiResponse::success(
            ['property' => PropertyResource::make($property->fresh())->resolve()],
            'Property updated.'
        );
    }

    public function destroy(Property $property): JsonResponse
    {
        $property->delete();

        return ApiResponse::success([], 'Property deleted.');
    }
}
