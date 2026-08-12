<?php

namespace App\Entity;

use App\Repository\CommunicationContractRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contrato de precio/visibilidad para un CommunicationPackage concreto
 * (rediseño V2). El match con el paquete es SIEMPRE por el FK
 * `communicationPackage` — nunca por la tupla (destinationAmount/
 * destinationCurrency), que aquí solo es un snapshot congelado en el
 * momento de crear el contrato (si el paquete cambia después, los
 * contratos ya emitidos no se alteran).
 *
 * `tenant === null` = contrato "por defecto": aplica a cualquier cuenta sin
 * contrato propio para este paquete, con el mismo efecto de
 * visibilidad/precio que uno específico (ver PackageCatalogResolver, Fase
 * 2). Sin `isActive`: la vigencia es solo `startAt`/`endAt` — "pausar" es
 * cerrar (`endAt = now()`) y crear uno nuevo si se reactiva.
 */
#[ORM\Entity(repositoryClass: CommunicationContractRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CommunicationContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommunicationPackage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationPackage $communicationPackage = null;

    /**
     * null = contrato "por defecto" (aplica a cualquier cuenta sin contrato
     * propio para este paquete).
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Account $tenant = null;

    /**
     * Snapshot de CommunicationPackage::$destinationAmount al crear el
     * contrato — no se usa para buscar (eso es el FK), solo auditoría.
     */
    #[ORM\Column]
    private ?float $destinationAmount = null;

    #[ORM\Column(length: 10)]
    private ?string $destinationCurrency = null;

    /**
     * Lo que se le cobra al cliente. CHECK >= 0 a nivel de esquema (un
     * contrato en $0 es un precio real y deliberado, ej. cortesía).
     */
    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Auditoría — mueve dinero real. ON DELETE SET NULL: si el usuario que
     * lo creó se borra, el contrato sigue vigente.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->startAt = new \DateTimeImmutable();
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

    public function getTenant(): ?Account
    {
        return $this->tenant;
    }

    public function setTenant(?Account $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getDestinationAmount(): ?float
    {
        return $this->destinationAmount;
    }

    public function setDestinationAmount(float $destinationAmount): static
    {
        $this->destinationAmount = $destinationAmount;

        return $this;
    }

    public function getDestinationCurrency(): ?string
    {
        return $this->destinationCurrency;
    }

    public function setDestinationCurrency(string $destinationCurrency): static
    {
        $this->destinationCurrency = $destinationCurrency;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Vigente en el instante dado: sin fecha de fin, o fin en el futuro, y
     * ya empezado.
     */
    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        if ($this->startAt !== null && $this->startAt > $now) {
            return false;
        }

        return $this->endAt === null || $this->endAt > $now;
    }

    /**
     * "Pausar" un contrato: cerrarlo ahora mismo. No hay `isActive` — cerrar
     * es la única forma de desactivar (crear uno nuevo si se reactiva).
     */
    public function close(?\DateTimeImmutable $at = null): static
    {
        $this->endAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    #[ORM\PrePersist]
    public function onCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PostPersist]
    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    public function onUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
