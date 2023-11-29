<?php

namespace App\Entity;

use App\Repository\EnvAuthRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnvAuthRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EnvAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, unique: true)]
    private ?string $tokenAuth = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $permission = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenAuth(): ?string
    {
        return $this->tokenAuth;
    }

    public function setTokenAuth(string $tokenAuth): static
    {
        $this->tokenAuth = $tokenAuth;

        return $this;
    }

    public function getPermission(): ?Account
    {
        return $this->permission;
    }

    public function setPermission(?Account $permission): static
    {
        $this->permission = $permission;

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

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PreRemove]
    public function setRemoved(): void
    {
        $this->closedAt = new DateTimeImmutable('now');
    }
}
