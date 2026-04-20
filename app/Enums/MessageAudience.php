<?php

namespace App\Enums;

enum MessageAudience: string
{
    case All = 'all';
    case Specific = 'specific';
    case ActiveOnly = 'active_only';
    case TrialOnly = 'trial_only';
}
