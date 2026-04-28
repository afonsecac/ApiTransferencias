<?php

namespace App\DTO\Out;

final class SyncProductsOutDto
{
    public bool $synced;
    public array $items = [];
    public string $environmentType;
}
