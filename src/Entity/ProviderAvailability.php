<?php

namespace App\Entity;

use App\Enums\ProviderActionTypeEnum;
use App\Repository\ProviderAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Estado AUTO (ping) de disponibilidad de un proveedor por entorno. El
 * interruptor MANUAL sigue viviendo en sys_config
 * (provider.{code}.{type}.active, ver App\Provider\ProviderCredentialsResolver)
 * — esta tabla nunca lo escribe ni lo lee, solo guarda lo que decidió el
 * último ping y quién hizo el último cambio manual, para auditoría y para
 * mostrar en el dashboard. Ver App\Service\Provider\ProviderAvailabilityService
 * para cómo se combinan ambos flags.
 */
#[ORM\Entity(repositoryClass: ProviderAvailabilityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_provider_availability', columns: ['provider', 'environment_type'])]
class ProviderAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $provider = null;

    #[ORM\Column(name: 'environment_type', length: 10)]
    private ?string $environmentType = null;

    #[ORM\Column(name: 'auto_enabled')]
    private bool $autoEnabled = true;

    #[ORM\Column(name: 'last_action_type', length: 10, enumType: ProviderActionTypeEnum::class, nullable: true)]
    private ?ProviderActionTypeEnum $lastActionType = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'last_action_by_id', nullable: true)]
    private ?User $lastActionBy = null;

    #[ORM\Column(name: 'last_action_at', nullable: true)]
    private ?\DateTimeImmutable $lastActionAt = null;

    #[ORM\Column(name: 'last_action_reason', length: 255, nullable: true)]
    private ?string $lastActionReason = null;

    #[ORM\Column(name: 'last_ping_at', nullable: true)]
    private ?\DateTimeImmutable $lastPingAt = null;

    #[ORM\Column(name: 'last_ping_success', nullable: true)]
    private ?bool $lastPingSuccess = null;

    #[ORM\Column(name: 'last_ping_latency_ms', nullable: true)]
    private ?int $lastPingLatencyMs = null;

    #[ORM\Column(name: 'last_ping_error', type: Types::TEXT, nullable: true)]
    private ?string $lastPingError = null;

    #[ORM\Column(name: 'last_ping_details', type: Types::JSON, nullable: true)]
    private ?array $lastPingDetails = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at')]
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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getEnvironmentType(): ?string
    {
        return $this->environmentType;
    }

    public function setEnvironmentType(string $environmentType): static
    {
        $this->environmentType = $environmentType;

        return $this;
    }

    public function isAutoEnabled(): bool
    {
        return $this->autoEnabled;
    }

    public function setAutoEnabled(bool $autoEnabled): static
    {
        $this->autoEnabled = $autoEnabled;

        return $this;
    }

    public function getLastActionType(): ?ProviderActionTypeEnum
    {
        return $this->lastActionType;
    }

    public function setLastActionType(?ProviderActionTypeEnum $lastActionType): static
    {
        $this->lastActionType = $lastActionType;

        return $this;
    }

    public function getLastActionBy(): ?User
    {
        return $this->lastActionBy;
    }

    public function setLastActionBy(?User $lastActionBy): static
    {
        $this->lastActionBy = $lastActionBy;

        return $this;
    }

    public function getLastActionAt(): ?\DateTimeImmutable
    {
        return $this->lastActionAt;
    }

    public function setLastActionAt(?\DateTimeImmutable $lastActionAt): static
    {
        $this->lastActionAt = $lastActionAt;

        return $this;
    }

    public function getLastActionReason(): ?string
    {
        return $this->lastActionReason;
    }

    public function setLastActionReason(?string $lastActionReason): static
    {
        $this->lastActionReason = $lastActionReason;

        return $this;
    }

    public function getLastPingAt(): ?\DateTimeImmutable
    {
        return $this->lastPingAt;
    }

    public function setLastPingAt(?\DateTimeImmutable $lastPingAt): static
    {
        $this->lastPingAt = $lastPingAt;

        return $this;
    }

    public function isLastPingSuccess(): ?bool
    {
        return $this->lastPingSuccess;
    }

    public function setLastPingSuccess(?bool $lastPingSuccess): static
    {
        $this->lastPingSuccess = $lastPingSuccess;

        return $this;
    }

    public function getLastPingLatencyMs(): ?int
    {
        return $this->lastPingLatencyMs;
    }

    public function setLastPingLatencyMs(?int $lastPingLatencyMs): static
    {
        $this->lastPingLatencyMs = $lastPingLatencyMs;

        return $this;
    }

    public function getLastPingError(): ?string
    {
        return $this->lastPingError;
    }

    public function setLastPingError(?string $lastPingError): static
    {
        $this->lastPingError = $lastPingError;

        return $this;
    }

    public function getLastPingDetails(): ?array
    {
        return $this->lastPingDetails;
    }

    public function setLastPingDetails(?array $lastPingDetails): static
    {
        $this->lastPingDetails = $lastPingDetails;

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
