<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateSysConfigDto implements IInput
{
    #[OAProperty(description: 'Nueva clave de la variable. Debe ser única')]
    #[Assert\Length(max: 255)]
    protected ?string $propertyName;

    #[OAProperty(description: 'Nuevo valor de la variable')]
    protected ?string $propertyValue;

    #[OAProperty(description: 'Activar o desactivar la variable')]
    protected ?bool $isActive;

    #[OAProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer'], 'nullable' => true], description: 'IDs de clientes a los que aplica. null significa que aplica a todos')]
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
