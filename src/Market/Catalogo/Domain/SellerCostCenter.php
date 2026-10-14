<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Un centro de costo de la unidad del vendedor, tal como lo ve este contexto.
 */
final readonly class SellerCostCenter
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public bool $principal,
        public bool $active,
    ) {
    }
}
