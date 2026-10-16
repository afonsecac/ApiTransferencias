<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/warehouses/{id}/users. Si el correo ya es usuario se vincula; si no, el nombre y el apellido
 * son obligatorios para crearlo y se le envía la invitación de activación.
 */
class InviteWarehouseUserDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    protected ?string $email;

    #[Assert\Length(max: 60)]
    protected ?string $firstName;

    #[Assert\Length(max: 120)]
    protected ?string $lastName;

    protected ?bool $stock;

    protected ?bool $assembly;

    protected ?bool $transfers;

    protected ?bool $deliveries;

    /** Persona de un socio (rol externo): solo en almacenes de socio. Por defecto, personal de la plataforma. */
    protected ?bool $external;

    public function __construct(?string $email = null, ?string $firstName = null, ?string $lastName = null, ?bool $stock = null, ?bool $assembly = null, ?bool $transfers = null, ?bool $deliveries = null, ?bool $external = null)
    {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->stock = $stock;
        $this->assembly = $assembly;
        $this->transfers = $transfers;
        $this->deliveries = $deliveries;
        $this->external = $external;
    }

    public function getExternal(): ?bool
    {
        return $this->external;
    }

    public function setExternal(?bool $external): void
    {
        $this->external = $external;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getStock(): ?bool
    {
        return $this->stock;
    }

    public function setStock(?bool $stock): void
    {
        $this->stock = $stock;
    }

    public function getAssembly(): ?bool
    {
        return $this->assembly;
    }

    public function setAssembly(?bool $assembly): void
    {
        $this->assembly = $assembly;
    }

    public function getTransfers(): ?bool
    {
        return $this->transfers;
    }

    public function setTransfers(?bool $transfers): void
    {
        $this->transfers = $transfers;
    }

    public function getDeliveries(): ?bool
    {
        return $this->deliveries;
    }

    public function setDeliveries(?bool $deliveries): void
    {
        $this->deliveries = $deliveries;
    }
}
