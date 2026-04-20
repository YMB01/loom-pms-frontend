<?php

namespace App\Services;

use App\Models\Subscription;

class SubscriptionBillingGate
{
    public static function requiresUpgrade(?Subscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        $subscription->loadMissing('plan');

        if ($subscription->plan?->slug !== 'free') {
            return false;
        }

        return $subscription->trial_ends_at && $subscription->trial_ends_at->isPast();
    }
}
