<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationClientPackageRepository;
use App\State\CommunicationClientPackageProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationClientPackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
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
            description: 'List all available packages',
            name: 'Packages',
            provider: CommunicationClientPackageProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['comPackage:read']],
    denormalizationContext: ['groups' => ['comPackage:create', 'comPackage:update']],
    security: "is_granted('ROLE_COM_API_USER')",

)]
#[ApiFilter(DateFilter::class, properties: ['activeStartAt', 'activeEndAt'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'priceClientPackage.amount'], arguments: ['orderParameterName' => 'orderBy'])]
class CommunicationClientPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comPackage:read', 'comProm:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $activeStartAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['comPackage:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $activeEndAt = null;

    #[ORM\Column]
    #[ApiProperty(
        description: 'List of benefits',
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'additional_information' => [
                        'type' => 'string',
                    ],
                    'amount' => [
                        'type' => 'object',
                        'properties' => [
                            'base' => [
                                'type' => 'integer',
                            ],
                            'promotion_bonus' => [
                                'type' => 'integer',
                            ],
                            'total_excluding_tax' => [
                                'type' => 'integer',
                            ],
                            'total_including_tax' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['CREDITS', 'TALKTIME', 'DATA', 'SMS'],
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['CUP', 'USD', 'UNITS', 'MINUTES', 'GB', 'ILIM'],
                    ],
                    'unit_type' => [
                        'type' => 'string',
                        'enum' => ['CURRENCY', 'QUANTITY', 'DATA', 'TIME'],
                    ],
                    'schedule' => [
                        'type' => 'object',
                        'properties' => [
                            'start' => [
                                'type' => 'string',
                            ],
                            'end' => [
                                'type' => 'string',
                                'nullable' => true,
                                'default' => null,
                            ],
                        ],
                        'default' => null,
                        'nullable' => true,
                    ],
                ],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $benefits = [];

    #[ORM\Column(length: 255)]
    #[Groups(['comPackage:read', 'comProm:read'])]
    #[ApiProperty]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comPackage:read', 'comProm:read'])]
    #[Assert\NotNull]
    #[ApiProperty]
    private ?string $name = null;

    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET'],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $tags = [];

    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'enum' => ['Mobile', 'uSIM', 'Devices'],
                ],
                'subservice' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM'],
                        ],
                    ],
                ],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $service = [];

    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'amount' => [
                    'type' => 'number',
                    'format' => 'currency-number',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['CUP', 'MLC', 'USD'],
                    'types' => ['https://schema.org/priceCurrency'],
                ],
                'unit_type' => [
                    'type' => 'string',
                    'enum' => ['CURRENCY'],
                ],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $destination = [];

    #[ORM\Column(nullable: true)]
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'quantity' => [
                    'type' => 'integer',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['DAYS', 'MONTH', 'YEAR'],
                ],
            ],
            'nullable' => true,
            'default' => null,
        ]
    )]
    #[Groups(['comPackage:read'])]
    private ?array $validity = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['comPackage:read'])]
    #[ApiProperty]
    private ?string $knowMore = null;

    #[ORM\ManyToMany(targetEntity: CommunicationPromotions::class, mappedBy: 'products')]
    #[Groups(['comPackage:read'])]
    #[ApiProperty]
    private Collection $promotions;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationPricePackage $priceClientPackage = null;

    public function __construct()
    {
        $this->activeStartAt = new \DateTimeImmutable();
        $this->benefits = [];
        $this->tags = [];
        $this->service = [];
        $this->destination = [];
        $this->validity = [];
        $this->promotions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    #[ORM\PrePersist]
    public function onCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PostPersist]
    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    public function onUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBenefits(): array
    {
        $benefitsOut = [];
        $benefits = $this->benefits;
        if ($this->getPromotions()->count() > 0) {
            foreach ($this->getPromotions() as $promotion) {
                foreach ($promotion->getTerms() as $term) {
                    $temItem = (object)$term;
                    $posUnit = array_search($temItem->unit, array_column($benefits, 'unit'), true);
                    $posUnitType = array_search($temItem->unit_type, array_column($benefits, 'unit_type'), true);
                    $posType = array_search($temItem->type, array_column($benefits, 'type'), true);
                    if (is_numeric($posUnit) && $posUnit === $posUnitType && $posType === $posUnitType) {
                        $currentBenefit = $benefits[$posUnit];
                        $base = $currentBenefit['amount']['base'];
                        $promotionAmount = $currentBenefit['amount']['promotion_bonus'];
                        $operation = $temItem->amount['operation'];
                        if ($operation === 'MULTI') {
                            $promotionAmount = $base * $temItem->amount['promotion_bonus'];
                        } elseif ($operation === 'ADD') {
                            $promotionAmount += $temItem->amount['promotion_bonus'];
                        }

                        $total = $base + $promotionAmount;
                        $currentBenefit['amount']['promotion_bonus'] = $promotionAmount;
                        $currentBenefit['amount']['total_including_tax'] = $total;
                        $currentBenefit['amount']['total_excluding_tax'] = $total;
                        $benefitsOut[] = $currentBenefit;
                    } else {
                        $arrayInfo = array_merge([
                            'additional_information' => null,
                            'amount' => [],
                            'type' => 'CREDITS',
                            'unit' => 'CUP',
                            'unit_type' => 'CURRENCY',
                            'schedule' => [
                                'start' => null,
                                'end' => null,
                            ],
                        ], $term);
                        $amountMerged = array_merge([
                            'base' => 0,
                            'promotion_bonus' => 0,
                            'total_excluding_tax' => 0,
                            'total_including_tax' => 0,
                        ], $term['amount']);
                        $total = $amountMerged['base'] + $amountMerged['promotion_bonus'];
                        $amountMerged['total_excluding_tax'] = $total;
                        $amountMerged['total_including_tax'] = $total;
                        $arrayInfo['amount'] = $amountMerged;
                        $benefitsOut[] = $arrayInfo;
                    }
                }
            }
        } else {
            $benefitsOut = $benefits;
        }

        return $benefitsOut;
    }

    public function setBenefits(array $benefits): static
    {
        $this->benefits = $benefits;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getService(): array
    {
        return $this->service;
    }

    public function setService(array $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getDestination(): array
    {
        return $this->destination;
    }

    public function setDestination(array $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getValidity(): ?array
    {
        return $this->validity;
    }

    public function setValidity(?array $validity): static
    {
        $this->validity = $validity;

        return $this;
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

    /**
     * @return Collection<int, CommunicationPromotions>
     */
    public function getPromotions(): Collection
    {

        return $this->promotions->filter(function (CommunicationPromotions $promotion) {
            $currentDate = new \DateTimeImmutable();

            return (is_null($promotion->getEndAt()) || $promotion->getEndAt(
                    ) >= $currentDate) && $promotion->getStartAt() <= $currentDate;
        });
    }

    public function addPromotion(CommunicationPromotions $promotion): static
    {
        if (!$this->promotions->contains($promotion)) {
            $this->promotions->add($promotion);
            $promotion->addProduct($this);
        }

        return $this;
    }

    public function removePromotion(CommunicationPromotions $promotion): static
    {
        if ($this->promotions->removeElement($promotion)) {
            $promotion->removeProduct($this);
        }

        return $this;
    }

    public function getPriceClientPackage(): ?CommunicationPricePackage
    {
        return $this->priceClientPackage;
    }

    public function setPriceClientPackage(?CommunicationPricePackage $priceClientPackage): static
    {
        $this->priceClientPackage = $priceClientPackage;

        return $this;
    }
}
