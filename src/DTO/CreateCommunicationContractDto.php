<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta de UN CommunicationContract (V2) — CommunicationContractService::createSingle().
 * `tenantId` ausente/null = contrato "por defecto" (aplica a cualquier
 * cuenta sin contrato propio para ese paquete). Para dar de alta el mismo
 * precio a varios clientes a la vez, usar CreateCommunicationContractBatchDto
 * en su lugar.
 *
 * `tenantId` es un Client.id (mismo espacio que `clients` en
 * CreateCommunicationContractBatchDto), NO un Account.id — el servicio lo
 * resuelve a la cuenta ACTIVA de ese cliente en `environmentId` vía
 * TargetAccountResolver, igual que el alta en lote. `environmentId` es
 * obligatorio cuando se da `tenantId`.
 */
class CreateCommunicationContractDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $communicationPackageId;

    #[Assert\Positive]
    protected ?int $tenantId;

    #[Assert\Positive]
    protected ?int $environmentId;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $price;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    protected ?string $startAt;

    protected ?string $endAt;

    public function __construct(
        ?int $communicationPackageId = null,
        ?int $tenantId = null,
        ?int $environmentId = null,
        ?float $price = null,
        ?string $currency = null,
        ?string $startAt = null,
        ?string $endAt = null,
    ) {
        $this->communicationPackageId = $communicationPackageId;
        $this->tenantId = $tenantId;
        $this->environmentId = $environmentId;
        $this->price = $price;
        $this->currency = $currency;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
    }

    public function getCommunicationPackageId(): ?int { return $this->communicationPackageId; }
    public function setCommunicationPackageId(?int $v): void { $this->communicationPackageId = $v; }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): void { $this->tenantId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }
}
