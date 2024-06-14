<?php

namespace App\Entity;

use App\Repository\CommunicationPricePackageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationPricePackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CommunicationPricePackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationProduct $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationPrice $priceUsed = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(length: 3)]
    private ?string $priceCurrency = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $activeStartAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activeEndAt = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $knowMore = null;

    #[ORM\Column]
    private array $dataInfo = [];

    #[ORM\ManyToOne]
    private ?Account $tenant = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->isActive = true;
        $this->activeStartAt = new \DateTimeImmutable();
        $this->priceCurrency = 'CUP';
        $this->currency = 'USD';
        $this->dataInfo = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?CommunicationProduct
    {
        return $this->product;
    }

    public function setProduct(?CommunicationProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getPriceUsed(): ?CommunicationPrice
    {
        return $this->priceUsed;
    }

    public function setPriceUsed(?CommunicationPrice $priceUsed): static
    {
        $this->priceUsed = $priceUsed;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPriceCurrency(): ?string
    {
        return $this->priceCurrency;
    }

    public function setPriceCurrency(string $priceCurrency): static
    {
        $this->priceCurrency = $priceCurrency;

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

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getActiveStartAt(): ?\DateTimeImmutable
    {
        return $this->activeStartAt;
    }

    public function setActiveStartAt(\DateTimeImmutable $activeStartAt): static
    {
        $this->activeStartAt = $activeStartAt;

        return $this;
    }

    public function getActiveEndAt(): ?\DateTimeImmutable
    {
        return $this->activeEndAt;
    }

    public function setActiveEndAt(?\DateTimeImmutable $activeEndAt): static
    {
        $this->activeEndAt = $activeEndAt;

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

    #[ORM\PrePersist]
    public function onCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    #[ORM\PostPersist]
    #[ORM\PreFlush]
    public function onUpdated(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getKnowMore(): ?string
    {
        return $this->knowMore;
    }

    public function setKnowMore(?string $knowMore): static
    {
        $this->knowMore = $knowMore;

        return $this;
    }

    public function getDataInfo(): array
    {
        return $this->dataInfo;
    }

    public function setDataInfo(array $dataInfo): static
    {
        $this->dataInfo = $dataInfo;

        return $this;
    }

    public function getTenant(): ?Account
    {
        return $this->tenant;
    }

    public function setTenant(?Account $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
