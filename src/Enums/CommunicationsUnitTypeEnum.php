<?php

namespace App\Enums;

enum CommunicationsUnitTypeEnum: string
{
    case CURRENCY = 'CURRENCY';
    case QUANTITY = 'QUANTITY';
    case DATA = 'DATA';
    case TIME = 'TIME';
}
