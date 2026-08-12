<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modo "rate" de PackageContractService::createByRate(): el admin fija un
 * precio BASE (no el costo mayorista del proveedor) y un rate; el sistema
 * calcula amount = round(basePrice * rateValue, 2) — con conversión de
 * moneda si baseCurrency difiere de currency — y genera un contrato por
 * cada cliente objetivo (misma bifurcación que promociones: `clients`
 * vacío = todas las cuentas activas del entorno).
 */
class CreateRateContractDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $referencePackageId;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $basePrice;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $baseCurrency;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $rateValue;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    /**
     * Entorno cuyas cuentas activas se targetean cuando $clients viene
     * vacío/ausente (ver TargetAccountResolver). Siempre requerido: aunque
     * $clients traiga ids concretos, ancla a qué entorno pertenece el lote.
     */
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $environmentId;

    /** @var int[]|null vacío/ausente = todas las cuentas activas del entorno */
    protected ?array $clients;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    public function __construct(
        ?int $referencePackageId = null,
        ?float $basePrice = null,
        ?string $baseCurrency = null,
        ?float $rateValue = null,
        ?string $currency = null,
        ?int $environmentId = null,
        ?array $clients = null,
        ?string $name = null,
        ?string $description = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
    ) {
        $this->referencePackageId = $referencePackageId;
        $this->basePrice = $basePrice;
        $this->baseCurrency = $baseCurrency;
        $this->rateValue = $rateValue;
        $this->currency = $currency;
        $this->environmentId = $environmentId;
        $this->clients = $clients;
        $this->name = $name;
        $this->description = $description;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
    }

    public function getReferencePackageId(): ?int { return $this->referencePackageId; }
    public function setReferencePackageId(?int $v): void { $this->referencePackageId = $v; }

    public function getBasePrice(): ?float { return $this->basePrice; }
    public function setBasePrice(?float $v): void { $this->basePrice = $v; }

    public function getBaseCurrency(): ?string { return $this->baseCurrency; }
    public function setBaseCurrency(?string $v): void { $this->baseCurrency = $v; }

    public function getRateValue(): ?float { return $this->rateValue; }
    public function setRateValue(?float $v): void { $this->rateValue = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    /** @return int[]|null */
    public function getClients(): ?array { return $this->clients; }
    /** @param int[]|null $v */
    public function setClients(?array $v): void { $this->clients = $v; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }
}
