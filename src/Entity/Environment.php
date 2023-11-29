<?php

namespace App\Entity;

use App\Repository\EnvironmentRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EnvironmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: "unique_Provider_Info", fields: ["type", "providerName"]
)]
#[ORM\Index(
    fields: ["type"], name: "index_type_environment"
)]
#[ORM\Index(
    fields: ["providerName"], name: "index_provider_name"
)]
class Environment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $basePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scope = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tenantId = null;

    #[ORM\Column(length: 255)]
    private ?string $providerName = null;

    #[ORM\Column(length: 255)]
    private ?string $clientSecret = null;

    #[ORM\Column(length: 255)]
    private ?string $clientId = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column]
    private ?float $discount = null;

    #[ORM\Column(length: 3)]
    private ?string $discountType = null;

    public function __construct()
    {
        $this->discount = 0;
        $this->discountType = "%";
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): static
    {
        $this->basePath = $basePath;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(string $clientSecret): static
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getIsActiveAt(): ?DateTimeImmutable
    {
        return $this->isActiveAt;
    }

    public function setIsActiveAt(?DateTimeImmutable $isActiveAt): static
    {
        $this->isActiveAt = $isActiveAt;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getDiscountType(): ?string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
