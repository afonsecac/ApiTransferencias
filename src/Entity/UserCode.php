<?php

namespace App\Entity;

use App\Repository\UserCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCodeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userInfo = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $code = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invalidAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $emailValidated = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $created): static
    {
        $this->createdAt = $created;

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

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): static
    {
        $this->usedAt = $usedAt;

        return $this;
    }

    public function getInvalidAt(): ?\DateTimeImmutable
    {
        return $this->invalidAt;
    }

    public function setInvalidAt(?\DateTimeImmutable $invalidAt): static
    {
        $this->invalidAt = $invalidAt;

        return $this;
    }

    public function isEmailValidated(): ?bool
    {
        return $this->emailValidated;
    }

    public function setEmailValidated(?bool $emailValidated): static
    {
        $this->emailValidated = $emailValidated;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtNow(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreFlush]
    #[ORM\PreUpdate]
    public function setUpdatedAtNow(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
