<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\DTO\InputPaginationPackage;
use App\DTO\OutputPaginationPackage;
use App\Repository\CommunicationClientPackageRepository;
use App\State\CommunicationClientPackageProvider;
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
        ),
    ],
    normalizationContext: ['groups' => ['comPackage:read']],
    denormalizationContext: ['groups' => ['comPackage:create', 'comPackage:update']],
    order: ['packageClientPrice.amount' => 'DESC'],
    security: "is_granted('ROLE_COM_API_USER')"
)]
#[ApiFilter(DateFilter::class, properties: ['activeStartAt', 'activeEndAt'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'activeStartAt'], arguments: ['orderParameterName' => 'orderBy'])]
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
    #[Groups(['comPackage:read','comProm:read'])]
    #[Assert\NotNull]
    #[ApiProperty]
    private ?string $name = null;

    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET']
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
                    'enum' => ['Mobile', 'uSIM', 'Devices']
                ],
                'subservice' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM']
                        ]
                    ]
                ]
            ]
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
                    'types' => ['https://schema.org/priceCurrency']
                ],
                'unit_type' => [
                    'type' => 'string',
                    'enum' => ['CURRENCY']
                ]
            ]
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
                ]
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

    public function __construct()
    {
        $this->activeStartAt = new \DateTimeImmutable();
        $this->benefits = [];
        $this->tags = [];
        $this->service = [];
        $this->destination = [];
        $this->validity = [];
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
        return $this->benefits;
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
}
