<?php

namespace App\Entity;

use App\Repository\ClientProviderRoutingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Enrutado de proveedor por cliente (Fase 2 del enrutado multi-proveedor).
 * Es control de ADMISIÓN, no de despacho: decide qué proveedores puede
 * consumir un cliente (ver App\Provider\ProviderResolver::allowedForClient())
 * y cuál es el default para altas nuevas (resolveForAccount()). El
 * despacho/consulta de una venta ya creada lee el snapshot de
 * CommunicationSaleInfo::provider, nunca esta tabla.
 */
#[ORM\Entity(repositoryClass: ClientProviderRoutingRepository::class)]
class ClientProviderRouting
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Environment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Environment $environment = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $saleType = null;

    #[ORM\Column(length: 20)]
    private ?string $provider = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fallbackProvider = null;

    /**
     * Orden en que ProviderDispatchResolver (V2 Fase 2) prueba los
     * proveedores de un cliente al despachar una venta — menor = se intenta
     * primero. Añadida en la migración de V2 Fase 1 (con backfill); no la
     * lee ningún flujo de despacho todavía (ver
     * Version20260810120200 para el detalle del backfill).
     */
    #[ORM\Column(type: 'smallint')]
    private int $priority = 100;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    public function getSaleType(): ?string
    {
        return $this->saleType;
    }

    public function setSaleType(?string $saleType): static
    {
        $this->saleType = $saleType;

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

    public function getFallbackProvider(): ?string
    {
        return $this->fallbackProvider;
    }

    public function setFallbackProvider(?string $fallbackProvider): static
    {
        $this->fallbackProvider = $fallbackProvider;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable('now');

        return $this;
    }
}
