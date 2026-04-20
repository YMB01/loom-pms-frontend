<?php

namespace App\Enums;

enum InAppNotificationType: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Success = 'success';
    case Error = 'error';
}
