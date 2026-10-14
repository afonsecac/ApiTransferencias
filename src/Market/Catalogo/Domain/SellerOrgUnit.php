<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La unidad organizativa de un vendedor tal como la ve este contexto: solo lo que necesita para validar al
 * vendedor y para mostrarlo. La unidad es de otro contexto (Organización) y llega por el puerto SellerOrgUnits.
 */
final readonly class SellerOrgUnit
{
    /**
     * @param list<string> $types valores de tipo de la unidad (`seller`, `platform`, ...)
     * @param list<SellerCostCenter> $costCenters
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $types,
        public array $costCenters,
    ) {
    }

    public function isSeller(): bool
    {
        return in_array('seller', $this->types, true);
    }

    public function principalCostCenter(): ?SellerCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->principal && $center->active) {
                return $center;
            }
        }

        return null;
    }

    public function costCenter(int $id): ?SellerCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->id === $id) {
                return $center;
            }
        }

        return null;
    }

    public function activeCostCenter(int $id): ?SellerCostCenter
    {
        $center = $this->costCenter($id);

        return $center !== null && $center->active ? $center : null;
    }
}
