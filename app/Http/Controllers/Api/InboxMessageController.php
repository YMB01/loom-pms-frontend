<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageAudience;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\SystemMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\MessageRead;
use App\Models\SystemMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxMessageController extends Controller
{
    use InteractsWithCompany;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $company = Company::query()
            ->with('subscription')
            ->findOrFail($this->companyId());

        $messages = $this->visibleMessagesQuery($company, $user)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return ApiResponse::success([
            'messages' => SystemMessageResource::collection($messages),
        ], '');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $company = Company::query()
            ->with('subscription')
            ->findOrFail($this->companyId());

        $count = $this->visibleMessagesQuery($company, $user)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return ApiResponse::success(['unread_count' => $count], '');
    }

    public function markRead(Request $request, SystemMessage $message): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $company = Company::query()
            ->with('subscription')
            ->findOrFail($this->companyId());

        if (! $message->isVisibleToCompany($company)) {
            return ApiResponse::error('Message not found.', 404);
        }

        MessageRead::query()->firstOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()]
        );

        return ApiResponse::success([], 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $company = Company::query()
            ->with('subscription')
            ->findOrFail($this->companyId());

        $ids = $this->visibleMessagesQuery($company, $user)->pluck('id');

        if ($ids->isEmpty()) {
            return ApiResponse::success([], 'All messages marked as read.');
        }

        $now = now();
        $rows = $ids->map(fn (int $id) => [
            'message_id' => $id,
            'user_id' => $user->id,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        MessageRead::query()->upsert($rows, ['message_id', 'user_id'], ['read_at', 'updated_at']);

        return ApiResponse::success([], 'All messages marked as read.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SystemMessage>
     */
    private function visibleMessagesQuery(Company $company, User $user)
    {
        $sub = $company->subscription;

        return SystemMessage::query()
            ->select('messages.*')
            ->where(function ($q) use ($company, $sub) {
                $q->where('sent_to', MessageAudience::All)
                    ->orWhere(function ($q2) use ($company) {
                        $q2->where('sent_to', MessageAudience::Specific)
                            ->where('company_id', $company->id);
                    });

                if ($sub?->status === SubscriptionStatus::Active) {
                    $q->orWhere('sent_to', MessageAudience::ActiveOnly);
                }

                if ($sub?->status === SubscriptionStatus::Trial) {
                    $q->orWhere('sent_to', MessageAudience::TrialOnly);
                }
            })
            ->withExists(['reads as user_has_read' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
    }
}
