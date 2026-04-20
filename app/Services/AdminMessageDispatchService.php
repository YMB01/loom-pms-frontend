<?php

namespace App\Services;

use App\Enums\MessageAudience;
use App\Enums\SubscriptionStatus;
use App\Mail\AdminBroadcastMail;
use App\Models\Company;
use App\Models\SystemMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminMessageDispatchService
{
    /**
     * @return Collection<int, Company>
     */
    public function recipientCompanies(SystemMessage $message): Collection
    {
        return match ($message->sent_to) {
            MessageAudience::All => Company::query()->get(),
            MessageAudience::Specific => Company::query()
                ->whereKey($message->company_id)
                ->get(),
            MessageAudience::ActiveOnly => Company::query()
                ->whereHas('subscription', fn ($q) => $q->where('status', SubscriptionStatus::Active))
                ->get(),
            MessageAudience::TrialOnly => Company::query()
                ->whereHas('subscription', fn ($q) => $q->where('status', SubscriptionStatus::Trial))
                ->get(),
        };
    }

    public function sendEmails(SystemMessage $message): void
    {
        if (! $message->send_email) {
            return;
        }

        foreach ($this->recipientCompanies($message) as $company) {
            $email = $company->email;
            if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new AdminBroadcastMail($message));
            } catch (\Throwable $e) {
                Log::warning('Admin broadcast email failed: '.$e->getMessage(), [
                    'email' => $email,
                    'message_id' => $message->id,
                ]);
            }
        }
    }
}
