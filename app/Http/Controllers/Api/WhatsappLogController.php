<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatsappLogIndexRequest;
use App\Http\Resources\WhatsappLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\WhatsappLog;
use Illuminate\Http\JsonResponse;

class WhatsappLogController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(WhatsappLogIndexRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = WhatsappLog::query()
            ->forCompany($companyId)
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->validated('search'), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('message', 'like', $term)
                    ->orWhere('to_number', 'like', $term)
                    ->orWhere('trigger', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, WhatsappLogResource::class),
            ''
        );
    }
}
