<?php

namespace App\Enums;

enum NotificationLevelEnum: string
{
    case INFO     = 'INFO';
    case SUCCESS  = 'SUCCESS';
    case WARNING  = 'WARNING';
    case ERROR    = 'ERROR';
    case CRITICAL = 'CRITICAL';
}
