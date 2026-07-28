<?php

namespace App\Enums;

enum NotificationAudienceEnum: string
{
    case USER   = 'USER';
    case ROLE   = 'ROLE';
    case CLIENT = 'CLIENT';
    case GLOBAL = 'GLOBAL';
}
