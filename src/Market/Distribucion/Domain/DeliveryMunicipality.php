<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final readonly class DeliveryMunicipality
{
    public function __construct(public int $id, public string $name, public int $provinceId, public string $provinceName, public bool $selectable)
    {
    }
}
