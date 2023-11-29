<?php

namespace App\Entity;

use App\Repository\SysConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SysConfigRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SysConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $propertyName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $propertyValue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $clients = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function setPropertyName(string $propertyName): static
    {
        $this->propertyName = $propertyName;

        return $this;
    }

    public function getPropertyValue(): ?string
    {
        return $this->propertyValue;
    }

    public function setPropertyValue(string $propertyValue): static
    {
        $this->propertyValue = $propertyValue;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
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

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?\DateTimeImmutable $removedAt): static
    {
        $this->removedAt = $removedAt;

        return $this;
    }

    public function getClients(): ?array
    {
        return $this->clients;
    }

    public function setClients(?array $clients): static
    {
        $this->clients = $clients;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
