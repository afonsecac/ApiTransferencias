<?php

namespace App\Entity;

use App\Repository\CommunicationProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationProductRepository::class)]
class CommunicationProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $packageId = null;

    #[ORM\Column(length: 255)]
    private ?string $packageType = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $initialDate = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDateAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->initialDate = new \DateTimeImmutable('now');
        $this->endDateAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPackageId(): ?int
    {
        return $this->packageId;
    }

    public function setPackageId(int $packageId): static
    {
        $this->packageId = $packageId;

        return $this;
    }

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(string $packageType): static
    {
        $this->packageType = $packageType;

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

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getInitialDate(): ?\DateTimeImmutable
    {
        return $this->initialDate;
    }

    public function setInitialDate(\DateTimeImmutable $initialDate): static
    {
        $this->initialDate = $initialDate;

        return $this;
    }

    public function getEndDateAt(): ?\DateTimeImmutable
    {
        return $this->endDateAt;
    }

    public function setEndDateAt(?\DateTimeImmutable $endDateAt): static
    {
        $this->endDateAt = $endDateAt;

        return $this;
    }

    public function getProductType(): ?string
    {
        return $this->productType;
    }

    public function setProductType(?string $productType): static
    {
        $this->productType = $productType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
