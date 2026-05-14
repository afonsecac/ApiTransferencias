<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class CreateSysConfigDto implements IInput
{
    #[OAProperty(description: 'Clave única de la variable (ej: communications.dispatch.enabled)')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $propertyName;

    #[OAProperty(description: 'Valor de la variable (siempre almacenado como string)')]
    #[Assert\NotBlank]
    protected ?string $propertyValue;

    #[OAProperty(description: 'Indica si la variable está activa. Por defecto true')]
    protected ?bool $isActive;

    #[OAProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer'], 'nullable' => true], description: 'IDs de clientes a los que aplica esta variable. null significa que aplica a todos')]
    protected ?array $clients;

    public function __construct(
        ?string $propertyName = null,
        ?string $propertyValue = null,
        ?bool $isActive = null,
        ?array $clients = null,
    ) {
        $this->propertyName  = $propertyName;
        $this->propertyValue = $propertyValue;
        $this->isActive      = $isActive;
        $this->clients       = $clients;
    }

    public function getPropertyName(): ?string { return $this->propertyName; }
    public function setPropertyName(?string $v): void { $this->propertyName = $v; }

    public function getPropertyValue(): ?string { return $this->propertyValue; }
    public function setPropertyValue(?string $v): void { $this->propertyValue = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getClients(): ?array { return $this->clients; }
    public function setClients(?array $v): void { $this->clients = $v; }
}
