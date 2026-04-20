<?php

namespace App\Console\Commands;

use App\Services\RentAutomationService;
use Illuminate\Console\Command;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-monthly';

    protected $description = 'Generate monthly rent invoices for all companies (1st of month)';

    public function handle(RentAutomationService $automation): int
    {
        $n = $automation->generateMonthlyInvoicesForAllCompanies();

        $this->info("Created {$n} invoice(s).");

        return self::SUCCESS;
    }
}
