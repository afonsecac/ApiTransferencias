<?php

namespace App\DTO\Out;

final class NotificationReadOutDto
{
    public string $id;
    public bool $read;
    public int $unreadCount;
}
