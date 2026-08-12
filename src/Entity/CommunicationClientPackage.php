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
use App\Service\Pricing\ResolvedSalePrice;
use App\State\CommunicationClientPackageItemProvider;
use App\State\CommunicationClientPackageProvider;
use App\State\UpcomingPackagesProvider;
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
            provider: CommunicationClientPackageItemProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/communication/packages',
            description: 'List all available packages',
            name: 'Packages',
            provider: CommunicationClientPackageProvider::class
        ),
        new GetCollection(
            uriTemplate: '/communication/packages/upcoming',
            description: 'List packages available in upcoming promotions',
            name: 'UpcomingPackages',
            provider: UpcomingPackagesProvider::class,
            paginationEnabled: false,
        ),
    ],
    normalizationContext: ['groups' => ['comPackage:read']],
    denormalizationContext: ['groups' => ['comPackage:create', 'comPackage:update']],
    security: "is_granted('ROLE_COM_API_USER')",
    paginationMaximumItemsPerPage: 100,
)]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'priceClientPackage.amount',
], arguments: ['orderParameterName' => 'orderBy'])]
class CommunicationClientPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    #[Groups(['comPackage:read', 'comProm:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    /**
     * Cuenta dueña de esta fila. `null` = paquete REFERENCIA: no pertenece a
     * ningún cliente, es la plantilla administrable de la que se copian los
     * paquetes materializados por cuenta (ver PackageMaterializationService
     * y CommunicationClientPackageProvider). Antes de este rediseño era
     * obligatorio; se relaja a nullable para permitir la referencia.
     */
    #[ORM\ManyToOne]
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
    private Collection $promotionItems;

    #[Groups(['comPackage:read'])]
    #[ApiProperty]
    private array $promotions;

    private ?array $upcomingPromotions = null;

    /**
     * Contrato de precio CONGELADO (típicamente de una promoción). Cuando
     * está seteado y es un contrato vigente, PackageSalePriceResolver lo usa
     * con máxima prioridad. Nullable desde este rediseño: un paquete sin
     * contrato resuelve su precio vía `product` en su lugar (ver
     * resolveProduct()/PackageSalePriceResolver).
     */
    #[ORM\ManyToOne]
    private ?CommunicationPricePackage $priceClientPackage = null;

    /**
     * Producto/proveedor del que sale este paquete cuando no hay contrato
     * (`priceClientPackage === null`). En paquetes de contrato congelado
     * queda como referencia informativa; resolveProduct() prioriza
     * `priceClientPackage->getProduct()` en ese caso.
     */
    #[ORM\ManyToOne]
    private ?CommunicationProduct $product = null;

    /**
     * De qué paquete referencia (tenant IS NULL) se materializó esta fila.
     * Null en la propia referencia y en filas legacy pre-backfill. Sustituye
     * en la práctica a CommunicationPricePackage::$pricePackage, que nunca
     * se llegó a escribir en código real.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $referencePackage = null;

    /**
     * Solo relevante en el paquete referencia (tenant IS NULL): permite
     * desactivarlo sin borrarlo, deteniendo nuevas materializaciones sin
     * afectar los paquetes ya copiados a clientes.
     */
    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    #[Groups(['comPackage:read'])]
    private ?float $amount = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['comPackage:read'])]
    private ?string $currency = null;

    #[ORM\ManyToOne]
    private ?Environment $environment = null;

    /**
     * Precio resuelto en el momento (PackageSalePriceResolver), NO
     * persistido (transient). getAmount()/getCurrency() lo consultan antes
     * que los campos ORM crudos, así que el JSON de comPackage:read no
     * cambia de forma para consumidores existentes (app móvil,
     * integraciones) aunque el precio ya no salga de $amount/$currency.
     */
    private ?ResolvedSalePrice $resolvedSalePrice = null;

    public function __construct()
    {
        $this->activeStartAt = new \DateTimeImmutable();
        $this->benefits = [];
        $this->tags = [];
        $this->service = [];
        $this->destination = [];
        $this->validity = [];
        $this->promotionItems = new ArrayCollection();
        $this->promotions = [];
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
        $promotions = $this->upcomingPromotions !== null
            ? $this->upcomingPromotions
            : $this->getPromotionItems();

        return $this->mergeBenefitsWithPromotions($promotions);
    }

    private function mergeBenefitsWithPromotions(iterable $promotions): array
    {
        $benefitsOut = [];
        $benefits = $this->benefits;
        $size = is_countable($promotions) ? count($promotions) : iterator_count($promotions);

        if ($size === 0) {
            return $benefits;
        }

        foreach ($promotions as $promotion) {
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
                        $base += $temItem->amount['base'];
                        $promotionAmount += $temItem->amount['promotion_bonus'];
                    }

                    $total = $base + $promotionAmount;
                    $currentBenefit['amount']['base'] = $base;
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
        $promotions = $this->upcomingPromotions !== null
            ? $this->upcomingPromotions
            : $this->getPromotionItems();

        $validityInfo = $this->validity;
        foreach ($promotions as $promotion) {
            if ($promotion instanceof CommunicationPromotions && $promotion->getValidityInfo()) {
                $validityInfo = $promotion->getValidityInfo();
            }
        }
        return $validityInfo;
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

    public function getPromotionItems(): Collection
    {

        return $this->promotionItems->filter(function (CommunicationPromotions $promotion) {
            $currentDate = new \DateTimeImmutable();
            $isEndDate = is_null($promotion->getEndAt()) || $promotion->getEndAt() > $currentDate;
            $isStartDate = $promotion->getStartAt() <= $currentDate;
            return $isStartDate && $isEndDate;
        });
    }

    public function addPromotion(CommunicationPromotions $promotion): static
    {
        if (!$this->promotionItems->contains($promotion)) {
            $this->promotionItems->add($promotion);
            $promotion->addProduct($this);
        }

        return $this;
    }

    public function removePromotion(CommunicationPromotions $promotion): static
    {
        if ($this->promotionItems->removeElement($promotion)) {
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

    /**
     * Devuelve el precio RESUELTO (PackageSalePriceResolver) si ya se
     * inyectó en este request; si no, cae al snapshot crudo persistido. Así
     * el serializer (comPackage:read) y el dashboard ven siempre el precio
     * efectivo sin que cambie la forma del JSON.
     */
    public function getAmount(): ?float
    {
        return $this->resolvedSalePrice->amount ?? $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->resolvedSalePrice->currency ?? $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
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

    public function getProduct(): ?CommunicationProduct
    {
        return $this->product;
    }

    public function setProduct(?CommunicationProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getReferencePackage(): ?self
    {
        return $this->referencePackage;
    }

    public function setReferencePackage(?self $referencePackage): static
    {
        $this->referencePackage = $referencePackage;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getResolvedSalePrice(): ?ResolvedSalePrice
    {
        return $this->resolvedSalePrice;
    }

    public function setResolvedSalePrice(ResolvedSalePrice $resolvedSalePrice): static
    {
        $this->resolvedSalePrice = $resolvedSalePrice;

        return $this;
    }

    /**
     * De qué producto/proveedor sale este paquete: prioriza el contrato
     * congelado (priceClientPackage), que es lo que se venía usando; si no
     * hay, cae al $product directo. Único punto de la entidad que debe
     * consultar CommunicationSaleService::resolveAndGuardProvider() /
     * assertRechargeableProduct() para no ambigüedad de proveedor.
     */
    public function resolveProduct(): ?CommunicationProduct
    {
        return $this->priceClientPackage?->getProduct() ?? $this->product;
    }

    /**
     * Id del paquete referencia que identifica a este paquete para buscar
     * contrato: el propio id si esta fila YA es la referencia (tenant
     * null), o el de `referencePackage` si es una materialización. Usado
     * por PackageSalePriceResolver/CommunicationPricePackageRepository para
     * localizar contratos por (tenant, referencePackage).
     */
    public function resolveContractKey(): ?int
    {
        return $this->tenant === null ? $this->id : $this->referencePackage?->getId();
    }

    public function setUpcomingPromotions(array $promotions): void
    {
        $this->upcomingPromotions = $promotions;
    }

    public function getPromotions(): array
    {
        if ($this->upcomingPromotions !== null) {
            return $this->upcomingPromotions;
        }

        $this->promotions = [];
        if ($this->getPromotionItems()->count() > 0) {
            $this->promotions[] = $this->getPromotionItems()->first();
        }

        return $this->promotions;
    }

    public function addPromotionItem(CommunicationPromotions $promotionItem): static
    {
        if (!$this->promotionItems->contains($promotionItem)) {
            $this->promotionItems->add($promotionItem);
            $promotionItem->addProduct($this);
        }

        return $this;
    }

    public function removePromotionItem(CommunicationPromotions $promotionItem): static
    {
        if ($this->promotionItems->removeElement($promotionItem)) {
            $promotionItem->removeProduct($this);
        }

        return $this;
    }
}
