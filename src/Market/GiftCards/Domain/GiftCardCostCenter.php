<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final readonly class GiftCardCostCenter
{
    public function __construct(public int $id, public string $code, public string $name, public bool $principal, public bool $active)
    {
    }
}
