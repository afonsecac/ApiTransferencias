<?php

namespace App\Enums;

enum CommunicationStateEnum: string
{
    case RESERVED = 'Reserved';
    case PENDING = 'Pending';
    case REJECTED = 'Rejected';
    case COMPLETED = 'Completed';
    case FAILED = 'Failed';
}
