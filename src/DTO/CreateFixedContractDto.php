<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modo "precio a precio" de PackageContractService::createFixed(): el admin
 * fija un importe exacto para un cliente y un paquete referencia concretos.
 */
class CreateFixedContractDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $tenantId;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $referencePackageId;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $amount;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    public function __construct(
        ?int $tenantId = null,
        ?int $referencePackageId = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $name = null,
        ?string $description = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
    ) {
        $this->tenantId = $tenantId;
        $this->referencePackageId = $referencePackageId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->name = $name;
        $this->description = $description;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
    }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): void { $this->tenantId = $v; }

    public function getReferencePackageId(): ?int { return $this->referencePackageId; }
    public function setReferencePackageId(?int $v): void { $this->referencePackageId = $v; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $v): void { $this->amount = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }
}
