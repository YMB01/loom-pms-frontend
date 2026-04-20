<?php

namespace App\Http\Controllers\Api;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maintenance\IndexMaintenanceRequest;
use App\Http\Requests\Api\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Api\Maintenance\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenanceRequest;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(IndexMaintenanceRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = MaintenanceRequest::query()
            ->forCompany($companyId)
            ->with(['property', 'assignee'])
            ->orderByDesc('created_at');

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->validated('property_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', MaintenanceStatus::from($request->validated('status')));
        }

        if ($request->filled('priority')) {
            $query->where('priority', MaintenancePriority::from($request->validated('priority')));
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('unit', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, MaintenanceRequestResource::class),
            ''
        );
    }

    public function store(StoreMaintenanceRequest $request, InAppNotificationService $notify): JsonResponse
    {
        $data = $request->validated();
        $data['priority'] = MaintenancePriority::from($data['priority']);
        $data['status'] = isset($data['status'])
            ? MaintenanceStatus::from($data['status'])
            : MaintenanceStatus::Open;

        $maintenance = MaintenanceRequest::query()->create($data);
        $maintenance->load(['property', 'assignee']);

        $notify->notifyManagers(
            $this->companyId(),
            'New maintenance request',
            $maintenance->title,
        );

        return ApiResponse::success(
            ['maintenance_request' => MaintenanceRequestResource::make($maintenance)->resolve()],
            'Maintenance request created.',
            201
        );
    }

    public function show(MaintenanceRequest $maintenance): JsonResponse
    {
        $maintenance->load(['property', 'assignee']);

        return ApiResponse::success(
            ['maintenance_request' => MaintenanceRequestResource::make($maintenance)->resolve()],
            ''
        );
    }

    public function update(UpdateMaintenanceRequest $request, MaintenanceRequest $maintenance): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['priority'])) {
            $data['priority'] = MaintenancePriority::from($data['priority']);
        }
        if (isset($data['status'])) {
            $data['status'] = MaintenanceStatus::from($data['status']);
        }

        $maintenance->update($data);
        $maintenance->load(['property', 'assignee']);

        return ApiResponse::success(
            ['maintenance_request' => MaintenanceRequestResource::make($maintenance->fresh())->resolve()],
            'Maintenance request updated.'
        );
    }

    public function destroy(MaintenanceRequest $maintenance): JsonResponse
    {
        $maintenance->delete();

        return ApiResponse::success([], 'Maintenance request deleted.');
    }

    /** Status/priority counts for dashboard bar chart + filters. */
    public function statsSummary(): JsonResponse
    {
        $companyId = $this->companyId();

        return ApiResponse::success([
            'open' => MaintenanceRequest::query()->forCompany($companyId)->where('status', MaintenanceStatus::Open)->count(),
            'in_progress' => MaintenanceRequest::query()->forCompany($companyId)->where('status', MaintenanceStatus::InProgress)->count(),
            'resolved' => MaintenanceRequest::query()->forCompany($companyId)->where('status', MaintenanceStatus::Resolved)->count(),
            'urgent' => MaintenanceRequest::query()->forCompany($companyId)->where('priority', MaintenancePriority::Urgent)->count(),
        ], '');
    }
}
