<?php

namespace App\Console\Commands;

use App\Services\RentAutomationService;
use Illuminate\Console\Command;

class SendRentRemindersCommand extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'SMS reminders for invoices due in 3 days';

    public function handle(RentAutomationService $automation): int
    {
        $n = $automation->sendRentRemindersThreeDaysBefore();
        $this->info("Sent {$n} reminder(s).");

        return self::SUCCESS;
    }
}
