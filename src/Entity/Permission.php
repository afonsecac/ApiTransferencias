<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $tokenId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?\DateTimeImmutable $removedAt): static
    {
        $this->removedAt = $removedAt;

        return $this;
    }

    public function getTokenId(): ?Uuid
    {
        return $this->tokenId;
    }

    public function setTokenId(Uuid $tokenId): static
    {
        $this->tokenId = $tokenId;

        return $this;
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

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->isActive = false;
    }

    #[ORM\PostRemove]
    public function setRemoved(): void {
        $this->removedAt = new \DateTimeImmutable('now');
        $this->isActive = false;
    }
}
