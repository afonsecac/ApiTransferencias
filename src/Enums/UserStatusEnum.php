<?php

namespace App\Enums;

enum UserStatusEnum: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case BUSY = 'busy';
    case AWAY = 'away';
    case INVISIBLE = 'invisible';
}
