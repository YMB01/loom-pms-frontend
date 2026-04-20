<?php

namespace App\Models;

use App\Enums\SaasSubscriptionPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasSubscriptionPayment extends Model
{
    protected $table = 'saas_subscription_payments';

    protected $fillable = [
        'company_id',
        'plan_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'due_at',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaasSubscriptionPaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
