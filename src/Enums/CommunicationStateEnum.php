<?php

namespace App\Enums;

enum CommunicationStateEnum: string
{
    case CREATED = 'Created';
    case RESERVED = 'Reserved';
    case PENDING = 'Pending';
    case REJECTED = 'Rejected';
    case COMPLETED = 'Completed';
    case FAILED = 'Failed';
}
