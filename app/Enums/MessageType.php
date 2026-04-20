<?php

namespace App\Enums;

enum MessageType: string
{
    case Announcement = 'announcement';
    case Warning = 'warning';
    case Maintenance = 'maintenance';
    case Urgent = 'urgent';
}
