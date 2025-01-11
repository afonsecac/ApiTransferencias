<?php

namespace App\Util;

final class DashboardUtil
{
    public static int $LIMIT_DEFAULT = 20;

    static function generateUniqueCode(int $length = 10): string
    {
        return uniqid('', true);
    }
}