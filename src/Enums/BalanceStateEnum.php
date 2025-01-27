<?php

namespace App\Enums;

enum BalanceStateEnum: string
{
    case PENDING = "PENDING";
    case REVERSED = "REVERSED";
    case COMPLETED = "COMPLETED";
    case IMPUGNED = "IMPUGNED";
}
