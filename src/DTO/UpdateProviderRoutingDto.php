<?php

namespace App\DTO;

use App\Enums\CommunicationProviderEnum;
use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * El cliente (clientId) no es editable: identifica de qué fila se trata.
 * Para reasignar una fila a otro cliente, elimínala y crea una nueva.
 */
class UpdateProviderRoutingDto implements IInput
{
    #[OAProperty(description: 'ID del Environment (TEST/PROD) al que aplica. null = ambos entornos del cliente')]
    #[Assert\Positive]
    protected ?int $environmentId;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['recharge', 'sale'], 'nullable' => true], description: 'Tipo de venta al que aplica. null = ambos tipos')]
    #[Assert\Choice(choices: ['recharge', 'sale'])]
    protected ?string $saleType;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE']], description: 'Proveedor a usar')]
    #[Assert\Choice(callback: [CommunicationProviderEnum::class, 'values'])]
    protected ?string $provider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE'], 'nullable' => true], description: 'Proveedor de respaldo (reservado para failover, Fase 5)')]
    #[Assert\Choice(callback: [CommunicationProviderEnum::class, 'values'])]
    protected ?string $fallbackProvider;

    #[OAProperty(description: 'Nota interna sobre el motivo de este enrutado')]
    #[Assert\Length(max: 255)]
    protected ?string $notes;

    #[OAProperty(description: 'Activa o desactiva esta fila')]
    protected ?bool $isActive;

    public function __construct(
        ?int $environmentId = null,
        ?string $saleType = null,
        ?string $provider = null,
        ?string $fallbackProvider = null,
        ?string $notes = null,
        ?bool $isActive = null,
    ) {
        $this->environmentId = $environmentId;
        $this->saleType = $saleType;
        $this->provider = $provider;
        $this->fallbackProvider = $fallbackProvider;
        $this->notes = $notes;
        $this->isActive = $isActive;
    }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getSaleType(): ?string { return $this->saleType; }
    public function setSaleType(?string $v): void { $this->saleType = $v; }

    public function getProvider(): ?string { return $this->provider; }
    public function setProvider(?string $v): void { $this->provider = $v; }

    public function getFallbackProvider(): ?string { return $this->fallbackProvider; }
    public function setFallbackProvider(?string $v): void { $this->fallbackProvider = $v; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }
}
