<?php

namespace App\Enums;

enum NotificationTypeEnum: string
{
    case BALANCE_LOW                    = 'BALANCE_LOW';
    case BALANCE_CRITICAL                = 'BALANCE_CRITICAL';
    case SALE_FAILED                     = 'SALE_FAILED';
    case RECHARGE_FAILED                 = 'RECHARGE_FAILED';
    case SALE_RETRY_EXHAUSTED            = 'SALE_RETRY_EXHAUSTED';
    case DISPATCH_PENDING                = 'DISPATCH_PENDING';
    case CLIENT_CREATED                  = 'CLIENT_CREATED';
    case ACCOUNT_ACTIVATED               = 'ACCOUNT_ACTIVATED';
    case TWO_FACTOR_MANDATORY_PENDING    = 'TWO_FACTOR_MANDATORY_PENDING';
    case PROMOTION_CREATED               = 'PROMOTION_CREATED';
    case PROVIDER_UNAVAILABLE            = 'PROVIDER_UNAVAILABLE';
    case PROVIDER_RECOVERED              = 'PROVIDER_RECOVERED';
    case CUSTOM                          = 'CUSTOM';
}
