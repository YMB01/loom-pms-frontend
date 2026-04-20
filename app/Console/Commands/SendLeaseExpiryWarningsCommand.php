<?php

namespace App\Console\Commands;

use App\Services\RentAutomationService;
use Illuminate\Console\Command;

class SendLeaseExpiryWarningsCommand extends Command
{
    protected $signature = 'leases:expiry-warnings';

    protected $description = 'Warn tenants and managers about leases expiring within 30 days';

    public function handle(RentAutomationService $automation): int
    {
        $n = $automation->sendLeaseExpiryWarnings();
        $this->info("Processed {$n} lease(s).");

        return self::SUCCESS;
    }
}
