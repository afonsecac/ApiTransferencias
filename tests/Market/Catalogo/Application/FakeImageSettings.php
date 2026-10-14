<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;

final class FakeImageSettings implements ImageSettings
{
    public function __construct(private readonly int $maxBytes = 4_000_000, private readonly int $maxPerProduct = 8)
    {
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    public function maxPerProduct(): int
    {
        return $this->maxPerProduct;
    }
}
