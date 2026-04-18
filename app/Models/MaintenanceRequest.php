<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'unit',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('property', fn (Builder $q) => $q->where('company_id', $companyId));
    }
}
