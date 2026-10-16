<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/warehouses/{id}. Son los datos de la pestaña «Datos y socio»; el identificador no cambia.
 */
class UpdateWarehouseDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\Length(max: 300)]
    protected ?string $address;

    #[Assert\Positive]
    protected ?int $municipalityId;

    #[Assert\Positive]
    protected ?int $orgUnitId;

    #[Assert\Positive]
    protected ?int $costCenterId;

    protected ?bool $setsCoverage;

    protected ?bool $assemblesCombos;

    protected ?bool $seesSupplyCost;

    public function __construct(
        ?string $name = null,
        ?string $address = null,
        ?int $municipalityId = null,
        ?int $orgUnitId = null,
        ?int $costCenterId = null,
        ?bool $setsCoverage = null,
        ?bool $assemblesCombos = null,
        ?bool $seesSupplyCost = null,
    ) {
        $this->name = $name;
        $this->address = $address;
        $this->municipalityId = $municipalityId;
        $this->orgUnitId = $orgUnitId;
        $this->costCenterId = $costCenterId;
        $this->setsCoverage = $setsCoverage;
        $this->assemblesCombos = $assemblesCombos;
        $this->seesSupplyCost = $seesSupplyCost;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getMunicipalityId(): ?int
    {
        return $this->municipalityId;
    }

    public function setMunicipalityId(?int $municipalityId): void
    {
        $this->municipalityId = $municipalityId;
    }

    public function getOrgUnitId(): ?int
    {
        return $this->orgUnitId;
    }

    public function setOrgUnitId(?int $orgUnitId): void
    {
        $this->orgUnitId = $orgUnitId;
    }

    public function getCostCenterId(): ?int
    {
        return $this->costCenterId;
    }

    public function setCostCenterId(?int $costCenterId): void
    {
        $this->costCenterId = $costCenterId;
    }

    public function getSetsCoverage(): ?bool
    {
        return $this->setsCoverage;
    }

    public function setSetsCoverage(?bool $setsCoverage): void
    {
        $this->setsCoverage = $setsCoverage;
    }

    public function getAssemblesCombos(): ?bool
    {
        return $this->assemblesCombos;
    }

    public function setAssemblesCombos(?bool $assemblesCombos): void
    {
        $this->assemblesCombos = $assemblesCombos;
    }

    public function getSeesSupplyCost(): ?bool
    {
        return $this->seesSupplyCost;
    }

    public function setSeesSupplyCost(?bool $seesSupplyCost): void
    {
        $this->seesSupplyCost = $seesSupplyCost;
    }
}
