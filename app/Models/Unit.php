<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'unit_number',
        'type',
        'floor',
        'size_sqm',
        'rent_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size_sqm' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'status' => UnitStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('property', fn (Builder $q) => $q->where('company_id', $companyId));
    }
}
