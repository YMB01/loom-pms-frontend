<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RentAutomationService
{
    public function __construct(
        private readonly SMSService $sms,
        private readonly InAppNotificationService $notify,
    ) {}

    public function generateMonthlyInvoicesForAllCompanies(?Carbon $runAt = null): int
    {
        $runAt ??= Carbon::now();
        $year = $runAt->year;
        $month = $runAt->month;
        $dueDate = $runAt->copy()->endOfMonth();

        $total = 0;

        $companies = Company::query()->get();

        foreach ($companies as $company) {
            $leases = Lease::query()
                ->forCompany($company->id)
                ->where('status', LeaseStatus::Active)
                ->get();

            foreach ($leases as $lease) {
                $exists = Invoice::query()
                    ->where('lease_id', $lease->id)
                    ->whereYear('due_date', $year)
                    ->whereMonth('due_date', $month)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::transaction(function () use ($lease, $dueDate, $company, &$total): void {
                    $invoice = Invoice::query()->create([
                        'lease_id' => $lease->id,
                        'tenant_id' => $lease->tenant_id,
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate->toDateString(),
                        'status' => InvoiceStatus::Pending,
                    ]);
                    $total++;

                    $tenant = $lease->tenant;
                    if ($tenant) {
                        $this->sms->sendRentDue($tenant, $invoice);
                    }

                    $this->notify->notifyManagers(
                        $company->id,
                        'Invoice generated',
                        'Invoice #'.$invoice->id.' created. Due '.$dueDate->toDateString().'.',
                    );
                });
            }
        }

        return $total;
    }

    public function sendRentRemindersThreeDaysBefore(): int
    {
        $target = Carbon::today()->addDays(3);
        $count = 0;

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Pending)
            ->whereDate('due_date', $target->toDateString())
            ->with(['tenant.company'])
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->tenant) {
                $this->sms->sendRentDue($invoice->tenant, $invoice);
                $count++;
            }
        }

        return $count;
    }

    public function markOverdueAndNotify(): int
    {
        $count = 0;

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Pending)
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->with(['tenant.company'])
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue]);
            if ($invoice->tenant) {
                $this->sms->sendOverdueReminder($invoice->tenant, $invoice);
            }
            $cid = $invoice->tenant?->company_id;
            if ($cid) {
                $this->notify->notifyManagers(
                    $cid,
                    'Invoice overdue',
                    'Invoice #'.$invoice->id.' is overdue.',
                );
            }
            $count++;
        }

        return $count;
    }

    public function sendLeaseExpiryWarnings(): int
    {
        $end = Carbon::today()->addDays(30);
        $count = 0;

        $leases = Lease::query()
            ->where('status', LeaseStatus::Active)
            ->whereBetween('end_date', [Carbon::today()->toDateString(), $end->toDateString()])
            ->with(['tenant.company', 'unit.property'])
            ->get();

        foreach ($leases as $lease) {
            $lease->update(['status' => LeaseStatus::Expiring]);
            $tenant = $lease->tenant;
            if ($tenant) {
                $this->sms->sendLeaseExpiryWarning($tenant, $lease);
            }
            if ($tenant?->company_id) {
                $this->notify->notifyManagers(
                    $tenant->company_id,
                    'Lease expiring soon',
                    'Lease #'.$lease->id.' for '.$tenant->name.' ends '.$lease->end_date->toDateString().'.',
                );
            }
            $count++;
        }

        return $count;
    }
}
