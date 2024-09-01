<?php

namespace App\Entity;

use App\Repository\CommunicationPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\Void_;

#[ORM\Entity(repositoryClass: CommunicationPriceRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class CommunicationPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $startPrice = null;

    #[ORM\Column(nullable: true)]
    private ?float $endPrice = null;

    #[ORM\Column(length: 3)]
    private ?string $currencyPrice = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $validStartAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validEndAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function __construct()
    {
        $this->isActive = true;
        $this->validStartAt = new \DateTimeImmutable();
        $this->currencyPrice = 'CUP';
        $this->currency = 'USD';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartPrice(): ?float
    {
        return $this->startPrice;
    }

    public function setStartPrice(float $startPrice): static
    {
        $this->startPrice = $startPrice;

        return $this;
    }

    public function getEndPrice(): ?float
    {
        return $this->endPrice;
    }

    public function setEndPrice(?float $endPrice): static
    {
        $this->endPrice = $endPrice;

        return $this;
    }

    public function getCurrencyPrice(): ?string
    {
        return $this->currencyPrice;
    }

    public function setCurrencyPrice(string $currencyPrice): static
    {
        $this->currencyPrice = $currencyPrice;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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

    public function getValidStartAt(): ?\DateTimeImmutable
    {
        return $this->validStartAt;
    }

    public function setValidStartAt(\DateTimeImmutable $validStartAt): static
    {
        $this->validStartAt = $validStartAt;

        return $this;
    }

    public function getValidEndAt(): ?\DateTimeImmutable
    {
        return $this->validEndAt;
    }

    public function setValidEndAt(?\DateTimeImmutable $validEndAt): static
    {
        $this->validEndAt = $validEndAt;

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

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[ORM\PrePersist]
    public function onCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    #[ORM\PostPersist]
    public function onUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
}
