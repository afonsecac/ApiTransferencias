<?php

namespace App\DTO\Out;

class JobPositionOutDto
{
    public int $id;
    public string $code;
    public string $name;
    public string $area;
    public bool $isActive;
    public ?string $createdAt;
    public ?string $updatedAt;
}
