<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use InteractsWithCompany;

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $userId = $request->user()->id;

        $rows = InAppNotification::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(['notifications' => $rows], '');
    }

    public function read(InAppNotification $in_app_notification, Request $request): JsonResponse
    {
        $this->assertOwns($in_app_notification, $request);

        $in_app_notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success([], 'Marked read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $userId = $request->user()->id;

        InAppNotification::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success([], 'All marked read.');
    }

    public function count(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $userId = $request->user()->id;

        $n = InAppNotification::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return ApiResponse::success(['unread_count' => $n], '');
    }

    private function assertOwns(InAppNotification $notification, Request $request): void
    {
        if ($notification->company_id !== $this->companyId()
            || $notification->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
