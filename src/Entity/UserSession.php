<?php

namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userBySession = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $originIp = null;

    #[ORM\Column]
    private array $anotherInfo = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastAccessAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserBySession(): ?User
    {
        return $this->userBySession;
    }

    public function setUserBySession(?User $userBySession): static
    {
        $this->userBySession = $userBySession;

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

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getOriginIp(): ?string
    {
        return $this->originIp;
    }

    public function setOriginIp(string $originIp): static
    {
        $this->originIp = $originIp;

        return $this;
    }

    public function getAnotherInfo(): array
    {
        return $this->anotherInfo;
    }

    public function setAnotherInfo(array $anotherInfo): static
    {
        $this->anotherInfo = $anotherInfo;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreFlush]
    #[ORM\PreUpdate]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getLastAccessAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessAt;
    }

    public function setLastAccessAt(\DateTimeImmutable $lastAccessAt): static
    {
        $this->lastAccessAt = $lastAccessAt;

        return $this;
    }
}
