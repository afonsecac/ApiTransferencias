<?php

namespace App\Enums;

enum RebusStatusEnum: int {
    case Created = 0;
    case Sent = 1;
    case Accepted = 2;
    case Completed = 3;
    case Rejected = 4;
}
