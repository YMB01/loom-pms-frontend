<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'max_properties',
        'max_units',
        'max_tenants',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimitedProperties(): bool
    {
        return $this->max_properties === null;
    }

    public function isUnlimitedUnits(): bool
    {
        return $this->max_units === null;
    }

    public function isUnlimitedTenants(): bool
    {
        return $this->max_tenants === null;
    }
}
