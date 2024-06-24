<?php

namespace App\Util;

use App\Enums\RebusStatusEnum;

abstract class RebusUtil
{
    public static function getRebusStatusName(RebusStatusEnum $statusEnum): string
    {
        return match ($statusEnum) {
            RebusStatusEnum::Sent => "Sent",
            RebusStatusEnum::Accepted => "Accepted",
            RebusStatusEnum::Completed => "Completed",
            RebusStatusEnum::Rejected => "Rejected",
            default => "Created",
        };
    }
}
