<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

final readonly class CostCenterInput
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $principal = false,
    ) {
    }
}
