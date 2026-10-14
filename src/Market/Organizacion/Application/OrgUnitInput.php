<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

/**
 * Datos de una unidad organizativa tal como llegan a un caso de uso (sin depender del transporte HTTP).
 */
final readonly class OrgUnitInput
{
    /**
     * @param list<string> $types valores de OrgUnitType
     * @param array<string, mixed> $fiscalData datos propios del país, validados contra su registro fiscal
     */
    public function __construct(
        public string $name,
        public string $legalName,
        public string $taxId,
        public string $address,
        public string $billingEmail,
        public array $types,
        public array $fiscalData,
    ) {
    }
}
