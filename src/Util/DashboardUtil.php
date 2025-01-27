<?php

namespace App\Util;

final class DashboardUtil
{
    public static int $LIMIT_DEFAULT = 20;

    /**
     * @throws \Random\RandomException
     */
    static function generateUniqueCode(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= strtoupper($characters[random_int(0, $charactersLength - 1)]);
        }

        return $randomString;
    }
}