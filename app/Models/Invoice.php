<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'amount',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('tenant', fn (Builder $q) => $q->where('company_id', $companyId));
    }

    public function syncStatusFromPayments(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $amount = (float) $this->amount;

        if ($totalPaid >= $amount) {
            $this->status = InvoiceStatus::Paid;
        } elseif ($totalPaid > 0) {
            $this->status = InvoiceStatus::Partial;
        } elseif (now()->toDateString() > $this->due_date->toDateString()) {
            $this->status = InvoiceStatus::Overdue;
        } else {
            $this->status = InvoiceStatus::Pending;
        }

        $this->save();
    }
}
