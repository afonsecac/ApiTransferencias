<?php

namespace App\Entity;

use App\Repository\CommunicationPackageProviderProductRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vínculo explícito paquete→producto POR PROVEEDOR (V2) — el admin fija a
 * mano qué CommunicationProduct de un proveedor concreto representa este
 * CommunicationPackage, en vez de dejar que ProviderDispatchResolver lo
 * busque automáticamente por tupla (destinationAmount/destinationCurrency).
 * Con vínculo, gana el vínculo; sin vínculo, el proveedor sigue
 * resolviéndose por matching automático como hasta ahora — ver
 * ProviderDispatchResolver::findDispatchableProduct().
 *
 * `provider` es un código de proveedor (mismo espacio que
 * CommunicationProduct::$provider / CommunicationProviderEnum), no una FK:
 * la lista de proveedores disponibles se descubre en runtime vía
 * ProviderRegistry::registered(), así que un proveedor nuevo no requiere
 * cambios de esquema aquí.
 */
#[ORM\Entity(repositoryClass: CommunicationPackageProviderProductRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_com_package_provider', columns: ['communication_package_id', 'provider'])]
class CommunicationPackageProviderProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommunicationPackage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CommunicationPackage $communicationPackage = null;

    #[ORM\Column(length: 20)]
    private ?string $provider = null;

    #[ORM\ManyToOne(targetEntity: CommunicationProduct::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CommunicationProduct $product = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommunicationPackage(): ?CommunicationPackage
    {
        return $this->communicationPackage;
    }

    public function setCommunicationPackage(CommunicationPackage $communicationPackage): static
    {
        $this->communicationPackage = $communicationPackage;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProduct(): ?CommunicationProduct
    {
        return $this->product;
    }

    public function setProduct(CommunicationProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
