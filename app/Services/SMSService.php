<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Payment;
use App\Models\Tenant;

class SMSService
{
    public function sendWelcome(Tenant $tenant): void
    {
        if (! filled($tenant->phone)) {
            return;
        }

        SendSmsJob::dispatch(
            $tenant->phone,
            "Welcome to Loom PMS, {$tenant->name}. We're glad you're here.",
            'tenant_welcome',
            $tenant->company_id
        );
    }

    public function sendPaymentReceived(Tenant $tenant, Payment $payment): void
    {
        if (! filled($tenant->phone)) {
            return;
        }

        SendSmsJob::dispatch(
            $tenant->phone,
            'We received a payment of '.$payment->amount.' for invoice #'.$payment->invoice_id.'. Thank you.',
            'payment_received',
            $tenant->company_id
        );
    }
}
