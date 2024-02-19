<?php

namespace App\Entity;

use App\Repository\CommunicationPriceTableRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationPriceTableRepository::class)]
class CommunicationPriceTable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?float $startPrice = null;

    #[ORM\Column(nullable: true)]
    private ?float $endPrice = null;

    #[ORM\Column(length: 3)]
    private ?string $rangePriceCurrency = null;

    #[ORM\Column]
    private ?int $productId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

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

    public function getStartPrice(): ?float
    {
        return $this->startPrice;
    }

    public function setStartPrice(?float $startPrice): static
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

    public function getRangePriceCurrency(): ?string
    {
        return $this->rangePriceCurrency;
    }

    public function setRangePriceCurrency(string $rangePriceCurrency): static
    {
        $this->rangePriceCurrency = $rangePriceCurrency;

        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

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
}
