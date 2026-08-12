<?php

namespace App\Entity;

use App\Repository\CommunicationPromotionProviderProductRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vínculo explícito promoción→producto POR PROVEEDOR — el admin fija a mano
 * qué CommunicationProduct de un proveedor concreto se despacha cuando esta
 * CommunicationPromotions termina resolviéndose a ese proveedor. Sin
 * vínculo, el producto "de origen" de la promoción (CommunicationPromotions::$product)
 * sigue sirviendo de fallback, pero SOLO si su proveedor coincide con el
 * candidato evaluado — ver PromotionProviderDispatchResolver.
 *
 * A diferencia de CommunicationPackageProviderProduct, aquí no hay matching
 * automático por tupla destino: una promoción no tiene un
 * destinationAmount/destinationCurrency único (genera tramos de precio por
 * cliente), así que toda asociación proveedor→producto debe ser explícita.
 *
 * `provider` es un código de proveedor (mismo espacio que
 * CommunicationProduct::$provider / CommunicationProviderEnum), no una FK:
 * la lista de proveedores disponibles se descubre en runtime vía
 * ProviderRegistry::registered().
 */
#[ORM\Entity(repositoryClass: CommunicationPromotionProviderProductRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_com_promotion_provider', columns: ['communication_promotion_id', 'provider'])]
class CommunicationPromotionProviderProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommunicationPromotions::class)]
    #[ORM\JoinColumn(name: 'communication_promotion_id', nullable: false, onDelete: 'CASCADE')]
    private ?CommunicationPromotions $promotion = null;

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

    public function getPromotion(): ?CommunicationPromotions
    {
        return $this->promotion;
    }

    public function setPromotion(CommunicationPromotions $promotion): static
    {
        $this->promotion = $promotion;

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
