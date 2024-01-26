<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\DTO\AccountBalanceDto;
use App\DTO\CreateOperationDto;
use App\Repository\BalanceOperationRepository;
use App\State\BalanceProvider;
use App\State\CreateOperationProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BalanceOperationRepository::class)]
#[ApiResource(
    uriTemplate: '/balance',
    operations: [
        new Get(
            uriTemplate: '/balance',
            defaults: ['color' => 'brown'],
            normalizationContext: [
                'groups' => ['balance:reading']
            ],
            output: AccountBalanceDto::class,
            provider: BalanceProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/balance/operations',
            normalizationContext: [
                'groups' => ['balance:read']
            ],
        ),
        new Post(
            uriTemplate: '/balance/operations',
            normalizationContext: [
                'groups' => ['balance:create']
            ],
            input: CreateOperationDto::class,
            processor: CreateOperationProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['balance:read', 'balance:reading']],
    denormalizationContext: ['groups' => ['balance:update', 'balance:create']],
)]
#[ORM\HasLifecycleCallbacks]
class BalanceOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['balance:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $currency = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?float $amountTax = null;

    #[ORM\Column(length: 3)]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $currencyTax = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?float $discount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $currencyDiscount = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?float $totalAmount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $totalCurrency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\ManyToOne]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    private ?CommunicationRecharge $recharge = null;

    #[ORM\Column(nullable: true)]
    private ?int $rechargeId = null;

    #[ORM\ManyToOne]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    private ?CommunicationSale $sale = null;

    #[ORM\Column(nullable: true)]
    private ?int $saleId = null;

    #[ORM\ManyToOne]
    #[Groups(['balance:read'])]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    private ?Transfer $transfer = null;

    #[ORM\Column(nullable: true)]
    private ?int $transferId = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['balance:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 10)]
    #[Groups(['balance:read'])]
    private ?string $state = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['balance:read'])]
    private ?string $operationType = null;

    public function __construct()
    {
        $this->amountTax = 0;
        $this->discount = 0;
        $this->currencyTax = 'USD';
        $this->currencyDiscount = 'USD';
        $this->totalAmount = 0;
        $this->totalCurrency = 'USD';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAmountTax(): ?float
    {
        return $this->amountTax;
    }

    public function setAmountTax(float $amountTax): static
    {
        $this->amountTax = $amountTax;

        return $this;
    }

    public function getCurrencyTax(): ?string
    {
        return $this->currencyTax;
    }

    public function setCurrencyTax(string $currencyTax): static
    {
        $this->currencyTax = $currencyTax;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getCurrencyDiscount(): ?string
    {
        return $this->currencyDiscount;
    }

    public function setCurrencyDiscount(string $currencyDiscount): static
    {
        $this->currencyDiscount = $currencyDiscount;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getTotalCurrency(): ?string
    {
        return $this->totalCurrency;
    }

    public function setTotalCurrency(string $totalCurrency): static
    {
        $this->totalCurrency = $totalCurrency;

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

    public function getRecharge(): ?CommunicationRecharge
    {
        return $this->recharge;
    }

    public function setRecharge(?CommunicationRecharge $recharge): static
    {
        $this->recharge = $recharge;

        return $this;
    }

    public function getRechargeId(): ?int
    {
        return $this->rechargeId;
    }

    public function setRechargeId(?int $rechargeId): static
    {
        $this->rechargeId = $rechargeId;

        return $this;
    }

    public function getSale(): ?CommunicationSale
    {
        return $this->sale;
    }

    public function setSale(?CommunicationSale $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }

    public function setSaleId(?int $saleId): static
    {
        $this->saleId = $saleId;

        return $this;
    }

    public function getTransfer(): ?Transfer
    {
        return $this->transfer;
    }

    public function setTransfer(?Transfer $transfer): static
    {
        $this->transfer = $transfer;

        return $this;
    }

    public function getTransferId(): ?int
    {
        return $this->transferId;
    }

    public function setTransferId(?int $transferId): static
    {
        $this->transferId = $transferId;

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

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getOperationType(): ?string
    {
        return $this->operationType;
    }

    public function setOperationType(?string $operationType): static
    {
        $this->operationType = $operationType;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreUpdate]
    #[ORM\PostPersist]
    #[ORM\PreFlush]
    #[ORM\PreRemove]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
