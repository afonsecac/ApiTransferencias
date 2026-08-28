<?php

namespace App\DTO;

use App\Enums\CommunicationProviderEnum;
use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CreateProviderRoutingDto implements IInput
{
    #[OAProperty(description: 'ID del cliente (empresa) al que aplica este enrutado')]
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $clientId;

    #[OAProperty(description: 'ID del Environment (TEST/PROD) al que aplica. null = ambos entornos del cliente')]
    #[Assert\Positive]
    protected ?int $environmentId;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['recharge', 'sale'], 'nullable' => true], description: 'Tipo de venta al que aplica. null = ambos tipos')]
    #[Assert\Choice(choices: ['recharge', 'sale'])]
    protected ?string $saleType;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE']], description: 'Proveedor a usar')]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [CommunicationProviderEnum::class, 'values'])]
    protected ?string $provider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE'], 'nullable' => true], description: 'Proveedor de respaldo: si el proveedor principal no puede despachar (no disponible) o rechaza el envío, se intenta este')]
    #[Assert\Choice(callback: [CommunicationProviderEnum::class, 'values'])]
    protected ?string $fallbackProvider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices', 'Utilities'], 'nullable' => true], description: 'Servicio al que aplica este enrutado. null = cualquier servicio')]
    #[Assert\Length(max: 255)]
    protected ?string $serviceName;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'uSIM'], 'nullable' => true], description: 'Subservicio al que aplica. Solo válido junto con serviceName. null = cualquier subservicio de esa categoría')]
    #[Assert\Length(max: 255)]
    protected ?string $subserviceName;

    #[OAProperty(description: 'Nota interna sobre el motivo de este enrutado')]
    #[Assert\Length(max: 255)]
    protected ?string $notes;

    public function __construct(
        ?int $clientId = null,
        ?int $environmentId = null,
        ?string $saleType = null,
        ?string $provider = null,
        ?string $fallbackProvider = null,
        ?string $serviceName = null,
        ?string $subserviceName = null,
        ?string $notes = null,
    ) {
        $this->clientId = $clientId;
        $this->environmentId = $environmentId;
        $this->saleType = $saleType;
        $this->provider = $provider;
        $this->fallbackProvider = $fallbackProvider;
        $this->serviceName = $serviceName;
        $this->subserviceName = $subserviceName;
        $this->notes = $notes;
    }

    #[Assert\Callback]
    public function validateServiceCategory(ExecutionContextInterface $context): void
    {
        if ($this->subserviceName !== null && $this->serviceName === null) {
            $context->buildViolation('subserviceName requiere serviceName')
                ->atPath('subserviceName')
                ->addViolation();
        }

        if ($this->serviceName !== null && str_contains($this->serviceName, '|')) {
            $context->buildViolation('serviceName no puede contener "|"')->atPath('serviceName')->addViolation();
        }
        if ($this->subserviceName !== null && str_contains($this->subserviceName, '|')) {
            $context->buildViolation('subserviceName no puede contener "|"')->atPath('subserviceName')->addViolation();
        }
    }

    public function getClientId(): ?int { return $this->clientId; }
    public function setClientId(?int $v): void { $this->clientId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getSaleType(): ?string { return $this->saleType; }
    public function setSaleType(?string $v): void { $this->saleType = $v; }

    public function getProvider(): ?string { return $this->provider; }
    public function setProvider(?string $v): void { $this->provider = $v; }

    public function getFallbackProvider(): ?string { return $this->fallbackProvider; }
    public function setFallbackProvider(?string $v): void { $this->fallbackProvider = $v; }

    public function getServiceName(): ?string { return $this->serviceName; }
    public function setServiceName(?string $v): void { $this->serviceName = $v; }

    public function getSubserviceName(): ?string { return $this->subserviceName; }
    public function setSubserviceName(?string $v): void { $this->subserviceName = $v; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes = $v; }
}
