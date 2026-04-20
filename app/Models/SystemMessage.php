<?php

namespace App\Models;

use App\Enums\MessageAudience;
use App\Enums\MessageType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemMessage extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'title',
        'body',
        'type',
        'sent_to',
        'company_id',
        'send_email',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'sent_to' => MessageAudience::class,
            'send_email' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }

    public function readByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads', 'message_id', 'user_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function isVisibleToCompany(Company $company): bool
    {
        return match ($this->sent_to) {
            MessageAudience::All => true,
            MessageAudience::Specific => $this->company_id !== null && (int) $this->company_id === (int) $company->id,
            MessageAudience::ActiveOnly => $company->subscription?->status === SubscriptionStatus::Active,
            MessageAudience::TrialOnly => $company->subscription?->status === SubscriptionStatus::Trial,
        };
    }
}
