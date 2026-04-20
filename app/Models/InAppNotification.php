<?php

namespace App\Models;

use App\Enums\InAppNotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    protected $table = 'in_app_notifications';

    protected $fillable = [
        'company_id', 'user_id', 'title', 'body', 'type', 'is_read', 'read_at', 'data',
    ];

    protected function casts(): array
    {
        return [
            'type' => InAppNotificationType::class,
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
