<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CommunicationSaleInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationSaleInfoRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['recharge' => CommunicationSaleRecharge::class, 'sale' => CommunicationSalePackage::class])]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: 'unique_transaction_id',
    fields: ['transactionId']
)]
#[ORM\UniqueConstraint(
    name: 'unique_identification_client',
    fields: ['clientTransactionId', 'tenant']
)]
#[ApiResource(
    uriTemplate: '/communication/sale'
)]
class CommunicationSaleInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $transactionOrder = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $clientTransactionId = null;

    #[ORM\Column]
    private ?float $amount = 0;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?int $packageId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationPackage $package = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column(nullable: true)]
    private ?float $discount = 0;

    #[ORM\Column(nullable: true)]
    private ?float $amountTax = 0;

    #[ORM\Column]
    private ?float $totalPrice = 0;

    public function __construct()
    {
        $this->discount = 0;
        $this->amountTax = 0;
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionOrder(): ?string
    {
        return $this->transactionOrder;
    }

    public function setTransactionOrder(?string $transactionOrder): static
    {
        $this->transactionOrder = $transactionOrder;

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;

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

    public function getClientTransactionId(): ?string
    {
        return $this->clientTransactionId;
    }

    public function setClientTransactionId(string $clientTransactionId): static
    {
        $this->clientTransactionId = $clientTransactionId;

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

    public function getPackageId(): ?int
    {
        return $this->packageId;
    }

    public function setPackageId(int $packageId): static
    {
        $this->packageId = $packageId;

        return $this;
    }

    public function getPackage(): ?CommunicationPackage
    {
        return $this->package;
    }

    public function setPackage(?CommunicationPackage $package): static
    {
        $this->package = $package;

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

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getAmountTax(): ?float
    {
        return $this->amountTax;
    }

    public function setAmountTax(?float $amountTax): static
    {
        $this->amountTax = $amountTax;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    #[ORM\PostPersist]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
