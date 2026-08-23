<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\Repository\CommunicationPackageRepository;
use App\Service\Pricing\ResolvedPackageOffer;
use App\Service\Pricing\ServiceCategoryKey;
use App\State\CommunicationPackageCatalogItemProvider;
use App\State\CommunicationPackageCatalogProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Catálogo agnóstico de proveedor (rediseño V2 — reemplaza a
 * CommunicationClientPackage/CommunicationPricePackage, ver plan de
 * "Reemplazo del sistema de paquetes/precios"). Una sola fila global, sin
 * tenant/product/environment: define solo cuánto se acredita al
 * destinatario (destinationAmount/destinationCurrency, en CUP por ahora).
 * Quién despacha la venta (qué proveedor/producto) y a qué precio se le
 * cobra al cliente se resuelven aparte — ver CommunicationContract y
 * PackageCatalogResolver/ProviderDispatchResolver (Fase 2).
 *
 * No hay UNIQUE sobre (destination_amount, destination_currency): dos
 * paquetes distintos pueden compartir tupla a propósito (ej. "Recarga 500
 * CUP" y "Bono navideño 500 CUP").
 *
 * `#[ApiResource]` en URI PROPIA (V2 Fase 3): `/communication/packages/catalog`.
 * CommunicationPackageCatalogProvider bypasea el provider Doctrine estándar
 * (PackageCatalogResolver ya decide el conjunto completo de paquetes
 * visibles, no solo su precio), así que CurrentUserExtension no llega a
 * intervenir en esta operación en absoluto.
 *
 * Desde el switch por cliente (ver CatalogVersionResolver), esta misma
 * entidad también se sirve bajo `/communication/packages` (la URI histórica
 * de CommunicationClientPackage/V1) para las cuentas marcadas V2 —
 * CommunicationClientPackageProvider/ItemProvider/UpcomingPackagesProvider
 * delegan aquí cuando corresponde. El grupo `comPackage:read` se mantiene
 * deliberadamente con el mismo shape que V1 (ver getPromotions() más abajo)
 * para que ambas URLs devuelvan JSON compatible.
 */
#[ORM\Entity(repositoryClass: CommunicationPackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    uriTemplate: '/communication/packages/catalog',
    operations: [
        new Get(
            uriTemplate: '/communication/packages/catalog/{id}',
            requirements: ['id' => '\d+'],
            provider: CommunicationPackageCatalogItemProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/communication/packages/catalog',
            description: 'Catálogo agnóstico de proveedor (V2) resuelto para el cliente autenticado',
            name: 'CommunicationPackageCatalog',
            provider: CommunicationPackageCatalogProvider::class,
            openapi: new OpenApiOperation(
                parameters: [
                    new OpenApiParameter(
                        name: 'orderBy[price]',
                        in: 'query',
                        description: 'Ordena por el precio resuelto para el cliente autenticado. Sin este parámetro, se ordena por id ascendente.',
                        schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
                    ),
                    new OpenApiParameter(
                        name: 'price[gte]',
                        in: 'query',
                        description: 'Precio mínimo (inclusive).',
                        schema: ['type' => 'number'],
                    ),
                    new OpenApiParameter(
                        name: 'price[lte]',
                        in: 'query',
                        description: 'Precio máximo (inclusive).',
                        schema: ['type' => 'number'],
                    ),
                ],
            ),
        ),
    ],
    normalizationContext: ['groups' => ['comPackage:read']],
    security: "is_granted('ROLE_COM_API_USER')",
    paginationMaximumItemsPerPage: 100,
)]
class CommunicationPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comPackage:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comPackage:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['comPackage:read'])]
    private ?string $knowMore = null;

    /**
     * Mismo shape que CommunicationClientPackage::$benefits — se conserva
     * para no romper la forma del JSON de cara a la app móvil (Fase 3).
     *
     * @var array<int, array<string, mixed>>
     */
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

    /**
     * @var list<string>
     */
    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'UNLIMITED'],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $tags = [];

    /**
     * @var array{name?: string, subservice?: array{name?: string}}
     */
    #[ORM\Column]
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'enum' => ['Mobile', 'uSIM', 'Devices', 'Utilities'],
                ],
                'subservice' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE', 'uSIM'],
                        ],
                    ],
                ],
            ],
        ]
    )]
    #[Groups(['comPackage:read'])]
    private array $service = [];

    /**
     * Derivada de `$service` (nombre+subservicio) por ServiceCategoryKey —
     * SE DERIVA EN setService(), nunca en un lifecycle callback (Doctrine ya
     * calculó el changeset cuando corre preUpdate; ver mismo razonamiento
     * en CommunicationContract::$serviceKey). Fase 1 del rediseño de
     * contratos por categoría (agosto 2026) — permite filtrar/indexar por
     * categoría en SQL sin queries JSON (sin precedente en este repo).
     * Default `'|'`, NO `''` — debe coincidir con lo que produce
     * `ServiceCategoryKey::of(null, null)` para un `service=[]` recién
     * construido (el separador siempre está presente); un default `''`
     * aquí divergería de la clave real que setService() derivaría.
     */
    #[ORM\Column(length: 191)]
    private string $serviceKey = '|';

    /**
     * @var array{quantity?: int, unit?: string}|null
     */
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

    /**
     * Monto que se acredita al destinatario (no el precio de venta — ese lo
     * decide CommunicationContract o el MAX de producto + margen). CHECK
     * > 0 a nivel de esquema.
     */
    #[ORM\Column]
    private ?float $destinationAmount = null;

    /**
     * Moneda/unidad de destinationAmount. Mismo ancho de columna que
     * CommunicationProduct::$destinationUnit para poder joinear/comparar
     * (ver DestinationKey).
     */
    #[ORM\Column(length: 10)]
    private ?string $destinationCurrency = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['comPackage:read'])]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $activeStartAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['comPackage:read'])]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $activeEndAt = null;

    #[ORM\Column]
    private int $displayOrder = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Precio/moneda de venta RESUELTO por PackageCatalogResolver (Fase 2),
     * NO persistido (transient) — mismo patrón que
     * CommunicationClientPackage::$resolvedSalePrice. getAmount()/getCurrency()
     * lo consultan; null hasta que el resolver lo inyecte.
     */
    private ?ResolvedPackageOffer $resolvedOffer = null;

    /**
     * Null = catálogo regular (sincronizado o creado a mano). No nulo =
     * este paquete fue generado por rango para una promoción V2 (ver
     * CommunicationPromotionService/Fase 5) — activo solo durante su
     * ventana (activeStartAt/activeEndAt heredados de la promoción) y
     * SIN fallback a auto-match en ProviderDispatchResolver: un
     * proveedor sin CommunicationPackageProviderProduct explícito para
     * este paquete simplemente no lo despacha.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'promotion_id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunicationPromotions $promotion = null;

    /**
     * Lado inverso de CommunicationContract::$packages — solo para el guard
     * de borrado (CommunicationPackageAdminService::delete()): un paquete
     * con al menos un contrato no se puede borrar (FK RESTRICT en la tabla
     * puente). No se usa para resolver catálogo/precio (eso sigue siendo
     * responsabilidad de PackageCatalogResolver desde el lado contrato).
     *
     * @var Collection<int, CommunicationContract>
     */
    #[ORM\ManyToMany(targetEntity: CommunicationContract::class, mappedBy: 'packages')]
    private Collection $contracts;

    public function __construct()
    {
        $this->benefits = [];
        $this->tags = [];
        // Vía setService(), no asignación directa — es el único punto que
        // deriva $serviceKey; el default de la propiedad ('|') es un
        // resguardo, no la fuente de verdad.
        $this->setService([]);
        $this->activeStartAt = new \DateTimeImmutable();
        $this->contracts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, CommunicationContract>
     */
    public function getContracts(): Collection
    {
        return $this->contracts;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getBenefits(): array
    {
        return $this->benefits;
    }

    public function setBenefits(array $benefits): static
    {
        $this->benefits = $benefits;

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
        $this->serviceKey = ServiceCategoryKey::fromService($service);

        return $this;
    }

    public function getServiceKey(): string
    {
        return $this->serviceKey;
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

    public function getDestinationAmount(): ?float
    {
        return $this->destinationAmount;
    }

    public function setDestinationAmount(float $destinationAmount): static
    {
        $this->destinationAmount = $destinationAmount;

        return $this;
    }

    public function getDestinationCurrency(): ?string
    {
        return $this->destinationCurrency;
    }

    public function setDestinationCurrency(string $destinationCurrency): static
    {
        $this->destinationCurrency = $destinationCurrency;

        return $this;
    }

    /**
     * Compatibilidad de forma con CommunicationClientPackage::getDestination()
     * — mismo shape `{amount, unit, unit_type}` que ya consume la app móvil.
     * Calculado directamente de los campos ORM, no depende de ningún
     * resolver.
     *
     * @return array{amount: ?float, unit: ?string, unit_type: string}
     */
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
    public function getDestination(): array
    {
        return [
            'amount' => $this->destinationAmount,
            'unit' => $this->destinationCurrency,
            'unit_type' => 'CURRENCY',
        ];
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

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
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

    public function getResolvedOffer(): ?ResolvedPackageOffer
    {
        return $this->resolvedOffer;
    }

    public function setResolvedOffer(ResolvedPackageOffer $resolvedOffer): static
    {
        $this->resolvedOffer = $resolvedOffer;

        return $this;
    }

    /**
     * Precio de venta RESUELTO (PackageCatalogResolver) si ya se inyectó en
     * este request; null si aún no se resolvió. A diferencia de
     * CommunicationClientPackage::getAmount(), no hay snapshot crudo al que
     * caer — este catálogo nunca tuvo precio propio, siempre es derivado.
     */
    #[Groups(['comPackage:read'])]
    public function getAmount(): ?float
    {
        return $this->resolvedOffer?->price;
    }

    #[Groups(['comPackage:read'])]
    public function getCurrency(): ?string
    {
        return $this->resolvedOffer?->currency;
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

    /**
     * Paridad de shape con CommunicationClientPackage::getPromotions() — V1
     * devuelve un array de 0 o 1 CommunicationPromotions
     * ($promotions[] = getPromotionItems()->first()). En V2 la relación es
     * un ManyToOne simple, así que el array se construye directo, sin
     * filtro de ventana: un paquete de promoción solo está en el catálogo
     * mientras su propia ventana lo esté (ver isActiveAt()/findActiveCatalog()),
     * y en /upcoming la promoción futura es justamente lo que se quiere
     * mostrar.
     *
     * @return list<CommunicationPromotions>
     */
    #[Groups(['comPackage:read'])]
    public function getPromotions(): array
    {
        return $this->promotion !== null ? [$this->promotion] : [];
    }

    /**
     * Vigente en el instante dado — mismo criterio que
     * CommunicationContract::isActiveAt(), aplicado al PAQUETE (no a su
     * contrato): PackageCatalogResolver::offersFromContracts() no filtra
     * por esto hoy (paquetes de promociones futuras pueden colarse en el
     * catálogo antes de tiempo si su contrato ya está vigente) — este
     * método es lo que usan CommunicationPackageCatalogProvider y
     * CatalogPackageVisibilityResolver para cerrar ese hueco, sin tocar la
     * precedencia de PackageCatalogResolver.
     */
    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        if (!$this->isActive) {
            return false;
        }
        if ($this->activeStartAt !== null && $this->activeStartAt > $now) {
            return false;
        }

        return $this->activeEndAt === null || $this->activeEndAt > $now;
    }
}
