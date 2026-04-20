<?php

namespace App\Console\Commands;

use App\Services\RentAutomationService;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark past-due pending invoices as overdue and notify';

    public function handle(RentAutomationService $automation): int
    {
        $n = $automation->markOverdueAndNotify();
        $this->info("Marked {$n} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
