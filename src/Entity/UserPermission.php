<?php

namespace App\Entity;

use App\Repository\UserPermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPermissionRepository::class)]
class UserPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    private ?User $userInfo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?NavigationItem $item = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(length: 50)]
    private ?string $minRoleRequired = null;

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

    public function getUserInfo(): ?User
    {
        return $this->userInfo;
    }

    public function setUserInfo(?User $userInfo): static
    {
        $this->userInfo = $userInfo;

        return $this;
    }

    public function getItem(): ?NavigationItem
    {
        return $this->item;
    }

    public function setItem(?NavigationItem $item): static
    {
        $this->item = $item;

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getMinRoleRequired(): ?string
    {
        return $this->minRoleRequired;
    }

    public function setMinRoleRequired(string $minRoleRequired): static
    {
        $this->minRoleRequired = $minRoleRequired;

        return $this;
    }
}
