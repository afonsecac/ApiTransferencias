<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(
    fields: ["companyIdentification", "companyIdentificationType"], name: "index_company_identification"
)]
#[ORM\UniqueConstraint(
    name: "unique_company_information", fields: ["companyCountry", "companyIdentification", "companyIdentificationType"]
)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 3)]
    private ?string $companyCountry = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $companyZipCode = null;

    #[ORM\Column(length: 120)]
    private ?string $companyEmail = null;

    #[ORM\Column(length: 20)]
    private ?string $companyPhoneNumber = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $removeAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column]
    private ?float $discountOfClient = null;

    #[ORM\Column(length: 255)]
    private ?string $companyIdentification = null;

    #[ORM\Column(length: 255)]
    private ?string $companyIdentificationType = null;

    public function __construct()
    {
        $this->isActive = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    public function getCompanyCountry(): ?string
    {
        return $this->companyCountry;
    }

    public function setCompanyCountry(string $companyCountry): static
    {
        $this->companyCountry = $companyCountry;

        return $this;
    }

    public function getCompanyZipCode(): ?string
    {
        return $this->companyZipCode;
    }

    public function setCompanyZipCode(?string $companyZipCode): static
    {
        $this->companyZipCode = $companyZipCode;

        return $this;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(string $companyEmail): static
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }

    public function getCompanyPhoneNumber(): ?string
    {
        return $this->companyPhoneNumber;
    }

    public function setCompanyPhoneNumber(string $companyPhoneNumber): static
    {
        $this->companyPhoneNumber = $companyPhoneNumber;

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

    public function getRemoveAt(): ?DateTimeImmutable
    {
        return $this->removeAt;
    }

    public function setRemoveAt(?DateTimeImmutable $removeAt): static
    {
        $this->removeAt = $removeAt;

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

    public function getDiscountOfClient(): ?float
    {
        return $this->discountOfClient;
    }

    public function setDiscountOfClient(float $discountOfClient): static
    {
        $this->discountOfClient = $discountOfClient;

        return $this;
    }

    public function getCompanyIdentification(): ?string
    {
        return $this->companyIdentification;
    }

    public function setCompanyIdentification(string $companyIdentification): static
    {
        $this->companyIdentification = $companyIdentification;

        return $this;
    }

    public function getCompanyIdentificationType(): ?string
    {
        return $this->companyIdentificationType;
    }

    public function setCompanyIdentificationType(string $companyIdentificationType): static
    {
        $this->companyIdentificationType = $companyIdentificationType;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PostRemove]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
