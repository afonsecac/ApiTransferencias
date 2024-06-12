<?php

namespace App\Enums;

enum CommunicationServiceEnum: string
{
    case RECHARGES = "RT";
    case CUBACEL_TUR = "CT";
    case MODEM_4G = "ME";
    case COMBINED_PACKAGES = "PQ";
    case DEVICES_ETECSA = "ET";
}
