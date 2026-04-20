<?php

namespace App\Enums;

enum SaasSubscriptionPaymentStatus: string
{
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Failed = 'failed';
}
