<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\DTO\ReserveRecharge;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleInfoRepository;
use App\State\CommunicationSaleProvider;
use App\State\CreateSaleInfoProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationSaleInfoRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 10)]
#[ORM\DiscriminatorMap(['recharge' => CommunicationSaleRecharge::class, 'sale' => CommunicationSalePackage::class])]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: 'unique_transaction_id',
    fields: ['transactionId']
)]
#[ORM\UniqueConstraint(
    name: 'unique_identification_client',
    fields: ['clientTransactionId', 'tenant'],

)]
#[ApiResource(
    uriTemplate: '/communication/sale',
    operations: [
        new Get(
            uriTemplate: '/communication/sale/{id}',
            defaults: ['color' => 'brown'],
            requirements: ['id' => '\d+'],
            provider: CommunicationSaleProvider::class
        ),
        new GetCollection(
            uriTemplate: '/communication/sale',
            order: ['id' => 'DESC']
        ),
        new Post(
            uriTemplate: '/communication/sale/recharge',
            input: CommunicationSaleRecharge::class,
            processor: CreateSaleInfoProcessor::class,
        ),
        new Post(
            uriTemplate: '/communication/sale/recharge/reserve',
            input: ReserveRecharge::class,
            processor: CreateSaleInfoProcessor::class,
        ),
        new Post(
            uriTemplate: '/communication/sale/package',
            input: CommunicationSalePackage::class,
            processor: CreateSaleInfoProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['comSales:read']],
    denormalizationContext: ['groups' => ['comSales:update', 'comSales:create']],
    security: "is_granted('ROLE_COM_API_USER')",
)]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
], arguments: ['orderParameterName' => 'orderBy'])]
class CommunicationSaleInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(
        identifier: true
    )]
    #[Groups(['comSales:read'])]
    protected ?int $id = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    protected ?string $transactionOrder = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    protected ?string $transactionId = null;

    #[ORM\Column]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    protected ?\DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    protected ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    #[ApiProperty(
        description: 'The transaction id on system of client, this info is unique',
        required: true
    )]
    #[Groups(['comSales:read', 'comSales:create'])]
    #[Assert\NotBlank]
    protected ?string $clientTransactionId = null;

    #[ORM\Column]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    protected ?float $amount = 0;

    #[ORM\Column(length: 3)]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'default' => 'USD',
            'example' => 'USD',
        ],
        types: 'https://schema.org/priceCurrency'
    )]
    #[Groups(['comSales:read'])]
    #[Assert\Length(min: 3, max: 3)]
    protected ?string $currency = null;

    #[ORM\Column]
    #[ApiProperty(
        description: 'The package id in current system, take the information from /communication/packages',
        required: true
    )]
    #[Assert\Positive]
    #[Assert\NotNull]
    #[Groups(['comSales:create'])]
    protected ?int $packageId = null;

    #[ORM\Column(nullable: true)]
    #[ApiProperty(
        description: 'The promotion id in current system, take the information from /communication/promotions',
    )]
    #[Assert\Positive]
    #[Groups(['comSales:create'])]
    protected ?int $promotionId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Account $tenant = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['comSales:read'])]
    #[ApiProperty]
    protected ?float $discount = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['comSales:read'])]
    #[ApiProperty]
    protected ?float $amountTax = 0;

    #[ORM\Column]
    #[Groups(['comSales:read'])]
    #[ApiProperty]
    protected ?float $totalPrice = 0;

    #[ORM\Column]
    #[Groups(['comSales:read'])]
    #[ApiProperty]
    protected array $transactionStatus = [];

    #[ORM\Column(length: 15)]
    #[Groups(['comSales:read'])]
    #[ApiProperty]
    protected ?CommunicationStateEnum $state = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ['recharge', 'sale'],
        ]
    )]
    #[ApiFilter(SearchFilter::class, strategy: SearchFilterInterface::STRATEGY_EXACT)]
    #[Groups(['comSales:read'])]
    public string $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationClientPackage $package = null;

    #[ORM\ManyToOne]
    private ?CommunicationPromotions $promotion = null;

    public function __construct()
    {
        $this->discount = 0;
        $this->amountTax = 0;
        $this->createdAt = new \DateTimeImmutable('now');
        $this->transactionStatus = [];
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

    public function getTransactionStatus(): array
    {
        return $this->transactionStatus;
    }

    public function setTransactionStatus(array $transactionStatus): static
    {
        $this->transactionStatus = $transactionStatus;

        return $this;
    }

    public function getState(): ?CommunicationStateEnum
    {
        return $this->state;
    }

    public function setState(CommunicationStateEnum $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getCalculatePrice(): void
    {
        $this->totalPrice = $this->amount + $this->amountTax - $this->discount;
    }

    public function getPackage(): ?CommunicationClientPackage
    {
        return $this->package;
    }

    public function setPackage(?CommunicationClientPackage $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getPromotion(): ?CommunicationPromotions
    {
        return $this->promotion;
    }

    public function setPromotion(?CommunicationPromotions $promotion): static
    {
        $this->promotion = $promotion;

        return $this;
    }

    public function getPromotionId(): ?int
    {
        return $this->promotionId;
    }

    public function setPromotionId(?int $promotionId): void
    {
        $this->promotionId = $promotionId;
    }
}
