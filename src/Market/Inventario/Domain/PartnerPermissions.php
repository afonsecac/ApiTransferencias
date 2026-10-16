<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/**
 * Lo que el socio puede hacer en su almacén. Solo se guarda: lo aplican las historias de cobertura, combos y acceso del socio.
 * En un almacén de la plataforma no aplica y se ignora.
 */
final readonly class PartnerPermissions
{
    public function __construct(
        public bool $setsCoverage = false,
        public bool $assemblesCombos = false,
        public bool $seesSupplyCost = false,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }
}
