<?php

namespace App\DTO\Out;

final class NotificationDismissOutDto
{
    public bool $deleted = true;
    public int $unreadCount;
}
