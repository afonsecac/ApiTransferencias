<?php

namespace App\Entity;

use App\Repository\EmailNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailNotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EmailNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?BalanceOperation $balanceIn = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $minInfo = null;

    #[ORM\Column(nullable: true)]
    private ?int $criticalTry = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBalanceIn(): ?BalanceOperation
    {
        return $this->balanceIn;
    }

    public function setBalanceIn(?BalanceOperation $balanceIn): static
    {
        $this->balanceIn = $balanceIn;

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

    public function getMinInfo(): ?int
    {
        return $this->minInfo;
    }

    public function setMinInfo(?int $minInfo): static
    {
        $this->minInfo = $minInfo;

        return $this;
    }

    public function getCriticalTry(): ?int
    {
        return $this->criticalTry;
    }

    public function setCriticalTry(?int $criticalTry): static
    {
        $this->criticalTry = $criticalTry;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }
    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
    #[ORM\PreFlush]
    public function setUpdated(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
