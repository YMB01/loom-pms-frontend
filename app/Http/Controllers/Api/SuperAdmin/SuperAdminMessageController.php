<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\MessageAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSystemMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\SystemMessage;
use App\Services\AdminMessageDispatchService;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminMessageController extends Controller
{
    public function __construct(
        protected AdminMessageDispatchService $dispatchService,
        protected InAppNotificationService $notify,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = SystemMessage::query()
            ->with('company:id,name,email')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (SystemMessage $m) {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'body' => $m->body,
                'type' => $m->type->value,
                'sent_to' => $m->sent_to->value,
                'company_id' => $m->company_id,
                'company' => $m->company
                    ? ['id' => $m->company->id, 'name' => $m->company->name, 'email' => $m->company->email]
                    : null,
                'send_email' => $m->send_email,
                'sent_at' => $m->sent_at?->toIso8601String(),
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success([
            'messages' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], '');
    }

    public function store(StoreSystemMessageRequest $request): JsonResponse
    {
        $data = DB::transaction(function () use ($request) {
            $sentTo = MessageAudience::from($request->validated('sent_to'));

            $message = SystemMessage::query()->create([
                'title' => $request->validated('title'),
                'body' => $request->validated('body'),
                'type' => $request->validated('type'),
                'sent_to' => $sentTo,
                'company_id' => $sentTo === MessageAudience::Specific
                    ? (int) $request->validated('company_id')
                    : null,
                'send_email' => (bool) $request->boolean('send_email', true),
                'sent_at' => now(),
            ]);

            $this->dispatchService->sendEmails($message);

            return $message->load('company:id,name,email');
        });

        $sentTo = MessageAudience::from($request->validated('sent_to'));
        if ($sentTo === MessageAudience::All) {
            foreach (Company::query()->pluck('id') as $cid) {
                $this->notify->broadcastToCompany((int) $cid, $data->title, $data->body);
            }
        } elseif ($sentTo === MessageAudience::Specific && $data->company_id) {
            $this->notify->broadcastToCompany((int) $data->company_id, $data->title, $data->body);
        }

        return ApiResponse::success([
            'message' => [
                'id' => $data->id,
                'title' => $data->title,
                'sent_at' => $data->sent_at?->toIso8601String(),
            ],
        ], 'Message sent.', 201);
    }
}
