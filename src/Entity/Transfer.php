<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\TransferRepository;
use App\State\CreateTransactionProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            validationContext: [
                'groups' => [Transfer::class, 'validationsTransactionType']
            ],
            processor: CreateTransactionProcessor::class
        ),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['tx:read']],
    denormalizationContext: ['groups' => ['tx:write']],
    security: "is_granted('ROLE_REM_API_USER')",
)]
class Transfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['tx:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['tx:read', 'tx:write'])]
    #[Assert\Positive]
    #[Assert\GreaterThan(value: 50)]
    #[Assert\LessThanOrEqual(value: 2000)]
    #[Assert\NotNull]
    private ?float $amountDeposit = null;

    #[ORM\Column(length: 3)]
    #[Groups(['tx:read', 'tx:write'])]
    #[ApiProperty(default: 'USD', types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: ['USD']
    )]
    private ?string $currency = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?float $amountCommission = null;

    #[ORM\Column(length: 3)]
    #[Groups(['tx:read'])]
    #[ApiProperty(default: 'USD', types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    #[Assert\Choice(
        choices: ['USD']
    )]
    private ?string $currencyCommission = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?float $totalAmount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['tx:read'])]
    #[ApiProperty(default: 'USD', types: ['https://schema.org/priceCurrency'])]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    #[Assert\Choice(
        choices: ['USD']
    )]
    private ?string $currencyTotal = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?float $rateToChange = null;

    #[Groups(['tx:read', 'tx:write'])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ['3', '5'],
            'description' => '3=Transfer to Beneficiary, 5=Charge amount to account',
            'example' => '3',
        ]
    )]
    #[Assert\Choice(choices: ['3', '5'])]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 1)]
    private ?string $transactionType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column]
    private ?int $rebusPayId = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['tx:read'])]
    private ?int $statusId = null;

    #[ORM\Column(length: 20)]
    #[Groups(['tx:read'])]
    private ?string $statusName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tx:read', 'tx:write'])]
    private ?string $reasonNote = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['tx:read', 'tx:write'])]
    #[Assert\NotNull(groups: ['debitTx'])]
    #[Assert\IsNull(groups: ['creditTx'])]
    private ?int $senderId = null;

    #[ORM\ManyToOne]
    private ?Sender $sender = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(groups: ['debitTx'])]
    #[Assert\IsNull(groups: ['creditTx'])]
    #[Groups(['tx:read', 'tx:write'])]
    private ?int $beneficiaryId = null;

    #[ORM\ManyToOne]
    private ?BankCard $beneficiary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiaryName = null;

    public function __construct()
    {
        $this->currency = 'USD';
        $this->currencyTotal = 'USD';
        $this->currencyCommission = 'USD';
    }

    public static function validationsTransactionType(self $transfer) {
        if ('5' !== $transfer->getTransactionType()) {
            return ['debitTx'];
        }
        return ['creditTx'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenderId(): ?int
    {
        return $this->senderId;
    }

    public function setSenderId(?int $senderId): static
    {
        $this->senderId = $senderId;

        return $this;
    }

    public function getSender(): ?Sender
    {
        return $this->sender;
    }

    public function setSender(?Sender $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getBeneficiaryId(): ?int
    {
        return $this->beneficiaryId;
    }

    public function setBeneficiaryId(?int $beneficiaryId): static
    {
        $this->beneficiaryId = $beneficiaryId;

        return $this;
    }

    public function getBeneficiary(): ?BankCard
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?BankCard $beneficiary): static
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    public function getAmountDeposit(): ?float
    {
        return $this->amountDeposit;
    }

    public function setAmountDeposit(float $amountDeposit): static
    {
        $this->amountDeposit = $amountDeposit;

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

    public function getAmountCommission(): ?float
    {
        return $this->amountCommission;
    }

    public function setAmountCommission(float $amountCommission): static
    {
        $this->amountCommission = $amountCommission;

        return $this;
    }

    public function getCurrencyCommission(): ?string
    {
        return $this->currencyCommission;
    }

    public function setCurrencyCommission(string $currencyCommission): static
    {
        $this->currencyCommission = $currencyCommission;

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

    public function getCurrencyTotal(): ?string
    {
        return $this->currencyTotal;
    }

    public function setCurrencyTotal(string $currencyTotal): static
    {
        $this->currencyTotal = $currencyTotal;

        return $this;
    }

    public function getRateToChange(): ?float
    {
        return $this->rateToChange;
    }

    public function setRateToChange(float $rateToChange): static
    {
        $this->rateToChange = $rateToChange;

        return $this;
    }

    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    public function setTransactionType(string $transactionType): static
    {
        $this->transactionType = $transactionType;

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

    public function getRebusPayId(): ?int
    {
        return $this->rebusPayId;
    }

    public function setRebusPayId(int $rebusPayId): static
    {
        $this->rebusPayId = $rebusPayId;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStatusId(): ?int
    {
        return $this->statusId;
    }

    public function setStatusId(int $statusId): static
    {
        $this->statusId = $statusId;

        return $this;
    }

    public function getStatusName(): ?string
    {
        return $this->statusName;
    }

    public function setStatusName(string $statusName): static
    {
        $this->statusName = $statusName;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function getReasonNote(): ?string
    {
        return $this->reasonNote;
    }

    public function setReasonNote(?string $reasonNote): static
    {
        $this->reasonNote = $reasonNote;

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getBeneficiaryName(): ?string
    {
        return $this->beneficiaryName;
    }

    public function setBeneficiaryName(?string $beneficiaryName): static
    {
        $this->beneficiaryName = $beneficiaryName;

        return $this;
    }
}
