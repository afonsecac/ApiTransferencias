<?php

namespace App\DTO\Out;

final class SyncProductsOutDto
{
    public bool $synced;
    public string $environmentType;
    /** Total de productos creados, sumado entre todos los proveedores. */
    public int $items = 0;
    /** @var list<array{provider: string, created: int, updated: int, skipped: int, error: ?string}> */
    public array $providers = [];
}
