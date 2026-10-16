<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final readonly class GiftCardActor
{
    public function __construct(public ?int $id, public string $name)
    {
    }
}
