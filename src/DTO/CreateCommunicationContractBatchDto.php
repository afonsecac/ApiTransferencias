<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta en lote de CommunicationContract (V2) — mismo precio para varios
 * clientes a la vez. Reutiliza TargetAccountResolver tal cual: `clients`
 * vacío/ausente = todas las cuentas activas de `environmentId` (mismo
 * criterio que CreateRateContractDto/promociones).
 */
class CreateCommunicationContractBatchDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $communicationPackageId;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $environmentId;

    /** @var int[]|null vacío/ausente = todas las cuentas activas del entorno */
    protected ?array $clients;

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
        ?int $environmentId = null,
        ?array $clients = null,
        ?float $price = null,
        ?string $currency = null,
        ?string $startAt = null,
        ?string $endAt = null,
    ) {
        $this->communicationPackageId = $communicationPackageId;
        $this->environmentId = $environmentId;
        $this->clients = $clients;
        $this->price = $price;
        $this->currency = $currency;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
    }

    public function getCommunicationPackageId(): ?int { return $this->communicationPackageId; }
    public function setCommunicationPackageId(?int $v): void { $this->communicationPackageId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    /** @return int[]|null */
    public function getClients(): ?array { return $this->clients; }
    /** @param int[]|null $v */
    public function setClients(?array $v): void { $this->clients = $v; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }
}
