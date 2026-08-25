<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
 *
 * `service` (Fase 3 del rediseño de contratos por categoría) es
 * obligatorio y debe coincidir con la categoría real de
 * `communicationPackageId` — CommunicationContractService lo valida contra
 * el paquete elegido (422 si no coincide). Exigirlo explícito, en vez de
 * derivarlo en silencio del paquete, es una decisión deliberada: atrapa el
 * caso de elegir el paquete equivocado en vez de propagarlo en silencio.
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

    #[Assert\NotNull]
    #[OAProperty(schema: [
        'type' => 'object',
        'properties' => [
            'name'       => ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices', 'Utilities']],
            'subservice' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'uSIM']],
            ]],
        ],
    ])]
    protected ?array $service;

    public function __construct(
        ?int $communicationPackageId = null,
        ?int $tenantId = null,
        ?int $environmentId = null,
        ?float $price = null,
        ?string $currency = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?array $service = null,
    ) {
        $this->communicationPackageId = $communicationPackageId;
        $this->tenantId = $tenantId;
        $this->environmentId = $environmentId;
        $this->price = $price;
        $this->currency = $currency;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->service = $service;
    }

    #[Assert\Callback]
    public function validateService(ExecutionContextInterface $context): void
    {
        if (empty($this->service['name'] ?? null)) {
            $context->buildViolation('service.name es requerido')->atPath('service')->addViolation();
        }
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

    public function getService(): ?array { return $this->service; }
    public function setService(?array $v): void { $this->service = $v; }
}
