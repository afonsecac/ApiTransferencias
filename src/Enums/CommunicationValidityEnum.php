<?php

namespace App\Enums;

enum CommunicationValidityEnum: string
{
    case DAY = 'DAY';
    case DAYS = 'DAYS';
    case HOUR = 'HOUR';
    case MINUTE = 'MINUTE';
    case SECOND = 'SECOND';
    case MONTH = 'MONTH';
    case YEAR = 'YEAR';
}
