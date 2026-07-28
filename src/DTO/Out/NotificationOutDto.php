<?php

namespace App\DTO\Out;

final class NotificationOutDto
{
    public string $id;
    public string $type;
    public string $level;
    public string $title;
    public ?string $body;
    public ?string $link;
    public bool $useRouter = true;
    public ?array $data;
    public string $audience;
    public int $groupCount;
    public ?int $environmentId;
    public bool $read;
    public string $createdAt;
    public ?string $expiresAt;
}
