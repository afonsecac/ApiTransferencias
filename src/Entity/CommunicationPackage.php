<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationPackageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationPackageRepository::class)]
#[ApiResource(
    uriTemplate: '/communication/packages',
    operations: [
        new Get(
            uriTemplate: '/communication/packages/{id}',
            defaults: ['color' => 'brown'],
            requirements: ['id' => '\d+'],
        ),
        new GetCollection(
            uriTemplate: '/communication/packages',
        ),
    ],
    normalizationContext: ['groups' => ['comPackage:read']],
    denormalizationContext: ['groups' => ['comPackage:create', 'comPackage:update']],
)]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(DateFilter::class, properties: ['startAt', 'endDateAt'])]
#[ApiFilter(SearchFilter::class, properties: ['packageType'])]
class CommunicationPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $comId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    private ?string $communicationDescription = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $endDateAt = null;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
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
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?float $comPrice = null;

    #[ORM\Column(length: 1)]
    private ?string $comPackageType = null;

    #[ORM\Column(length: 3)]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(default: 'CUP', types: 'https://scheme.org/priceCurrency')]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['CUP'])]
    private ?string $comCurrency = null;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => ['type' => 'json'],
            'example' => '[{"language": "es", "main": "Description in SP", "optional": "", "youKnow": [], "knowMore": "url" }, {"language": "en", "main": "Description in EN", "optional": "", "youKnow": [], "knowMore": "url" }]'
        ],
    )]
    private array $comInfo = [];

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[ApiProperty]
    private ?bool $isOffer = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnabled = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    #[ORM\ManyToOne]
    private ?Account $tenant = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[ApiProperty(
        description: 'Package Type. RT=Recharges, CT=Cubacel Tur ME=Modem 4G, PQ=Combined Packages, ET=Devices',
        openapiContext: [
            'type' => 'string',
            'enum' => ['RT', 'CT', 'ME', 'PQ', 'ET'],
            'example' => 'RT'
        ]
    )]
    #[Groups(['comPackage:read'])]
    private ?string $packageType = 'RT';

    public function __construct()
    {
        $this->isEnabled = true;
        $this->packageType = 'RT';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComId(): ?int
    {
        return $this->comId;
    }

    public function setComId(int $comId): static
    {
        $this->comId = $comId;

        return $this;
    }

    public function getCommunicationDescription(): ?string
    {
        return $this->communicationDescription;
    }

    public function setCommunicationDescription(string $communicationDescription): static
    {
        $this->communicationDescription = $communicationDescription;

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

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndDateAt(): ?\DateTimeImmutable
    {
        return $this->endDateAt;
    }

    public function setEndDateAt(\DateTimeImmutable $endDateAt): static
    {
        $this->endDateAt = $endDateAt;

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

    public function getComPrice(): ?float
    {
        return $this->comPrice;
    }

    public function setComPrice(float $comPrice): static
    {
        $this->comPrice = $comPrice;

        return $this;
    }

    public function getComPackageType(): ?string
    {
        return $this->comPackageType;
    }

    public function setComPackageType(string $comPackageType): static
    {
        $this->comPackageType = $comPackageType;

        return $this;
    }

    public function getComCurrency(): ?string
    {
        return $this->comCurrency;
    }

    public function setComCurrency(string $comCurrency): static
    {
        $this->comCurrency = $comCurrency;

        return $this;
    }

    public function getComInfo(): array
    {
        return $this->comInfo;
    }

    public function setComInfo(array $comInfo): static
    {
        $this->comInfo = $comInfo;

        return $this;
    }

    public function isIsOffer(): ?bool
    {
        return $this->isOffer;
    }

    public function setIsOffer(bool $isOffer): static
    {
        $this->isOffer = $isOffer;

        return $this;
    }

    public function isIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreFlush]
    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    public function setUpdated(): void {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): static
    {
        $this->environment = $environment;

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

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(?string $packageType): static
    {
        $this->packageType = $packageType;

        return $this;
    }
}
