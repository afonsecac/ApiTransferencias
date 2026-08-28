<?php

namespace App\DTO;

use App\Enums\CommunicationProviderEnum;
use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * El cliente (clientId) no es editable: identifica de qué fila se trata.
 * Para reasignar una fila a otro cliente, elimínala y crea una nueva.
 *
 * Convención de limpieza para serviceName/subserviceName: `null` = no
 * tocar el campo, `''` (cadena vacía) = limpiarlo a comodín. Es la única
 * forma de distinguir "no viene en el PATCH" de "se quiere volver a
 * comodín" cuando ambos casos parten de un valor no-null en la fila.
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

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE'], 'nullable' => true], description: 'Proveedor de respaldo: si el proveedor principal no puede despachar o rechaza el envío, se intenta este')]
    #[Assert\Choice(callback: [CommunicationProviderEnum::class, 'values'])]
    protected ?string $fallbackProvider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['Mobile', 'uSIM', 'Devices', 'Utilities'], 'nullable' => true], description: 'Servicio al que aplica. null = no tocar, "" = volver a comodín (cualquier servicio)')]
    #[Assert\Length(max: 255)]
    protected ?string $serviceName;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'uSIM'], 'nullable' => true], description: 'Subservicio. null = no tocar, "" = volver a comodín. Solo válido junto con serviceName')]
    #[Assert\Length(max: 255)]
    protected ?string $subserviceName;

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
        ?string $serviceName = null,
        ?string $subserviceName = null,
        ?string $notes = null,
        ?bool $isActive = null,
    ) {
        $this->environmentId = $environmentId;
        $this->saleType = $saleType;
        $this->provider = $provider;
        $this->fallbackProvider = $fallbackProvider;
        $this->serviceName = $serviceName;
        $this->subserviceName = $subserviceName;
        $this->notes = $notes;
        $this->isActive = $isActive;
    }

    #[Assert\Callback]
    public function validateServiceCategory(ExecutionContextInterface $context): void
    {
        // '' (limpiar serviceName) + subserviceName no vacío es la misma
        // combinación imposible que null + subserviceName no vacío.
        if (!empty($this->subserviceName) && empty($this->serviceName)) {
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

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }
}
