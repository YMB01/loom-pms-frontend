<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Terminated = 'terminated';
}
