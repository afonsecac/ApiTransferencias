<?php

namespace App\Entity;

use App\Repository\CommunicationProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommunicationProductRepository::class)]
class CommunicationProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $packageId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read'])]
    private ?string $packageType = null;

    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?float $price = null;

    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['product:read'])]
    private ?\DateTimeImmutable $initialDate = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['product:read'])]
    private ?\DateTimeImmutable $endDateAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $productType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:read'])]
    private ?Environment $environment = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $description = null;

    /**
     * Proveedor dueño de este producto (App\Enums\CommunicationProviderEnum).
     * Es la fuente de verdad del despacho: una venta hereda este valor al
     * crearse (ver CommunicationSaleService), no el routing del cliente.
     */
    #[ORM\Column(length: 20)]
    #[Groups(['product:read'])]
    private string $provider = 'ETECSA';

    /**
     * ID del producto en el sistema del proveedor. Reemplaza a packageId
     * como clave canónica del upsert de catálogo — packageId (int) se
     * conserva por compatibilidad con el código que ya lo lee, pero
     * externalRef (string) admite IDs no numéricos de futuros proveedores.
     */
    #[ORM\Column(length: 64)]
    #[Groups(['product:read'])]
    private string $externalRef = '';

    /**
     * Monto que se acredita al beneficiario (no el costo mayorista — ese es
     * `price`). Viene de ProviderProductDto::$destinationAmount.
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['product:read'])]
    private ?float $destinationAmount = null;

    /**
     * Moneda/unidad de destinationAmount (puede no ser una moneda: CUP,
     * USD, MINUTES, GB, ILIM...). Viene de ProviderProductDto::$destinationUnit.
     */
    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $destinationUnit = null;

    /**
     * Moneda del costo mayorista (`price`). Viene de
     * ProviderProductDto::$priceCurrency.
     */
    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $priceCurrency = null;

    /**
     * true si es servicio de telefonía móvil o Internet — lo único que
     * aplica a un enrutado con `saleType='recharge'` (ver
     * ClientCatalogImportService::matchesSaleType()). Viene de
     * ProviderProductDto::$isMobileOrInternetService. Default true: ETECSA
     * (todas las filas históricas) es exclusivamente telefonía móvil.
     */
    #[ORM\Column]
    #[Groups(['product:read'])]
    private bool $isMobileOrInternetService = true;

    /**
     * Viene de ProviderProductDto::$benefits — se conserva tal cual para que
     * ClientCatalogImportService pueda construir el CommunicationClientPackage
     * automáticamente (ver matchesSaleType() y createClientPackageIfMissing())
     * sin tener que re-derivar la estructura de beneficios.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['product:read'])]
    private array $benefits = [];

    /**
     * Forma `{name, subservice: {name}}` — mismo shape que
     * CommunicationClientPackage::$service. Viene de
     * ProviderProductDto::$service.
     *
     * @var array{name?: string, subservice?: array{name?: string}}
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['product:read'])]
    private array $service = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->initialDate = new \DateTimeImmutable('now');
        $this->endDateAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(string $packageType): static
    {
        $this->packageType = $packageType;

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

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getInitialDate(): ?\DateTimeImmutable
    {
        return $this->initialDate;
    }

    public function setInitialDate(\DateTimeImmutable $initialDate): static
    {
        $this->initialDate = $initialDate;

        return $this;
    }

    public function getEndDateAt(): ?\DateTimeImmutable
    {
        return $this->endDateAt;
    }

    public function setEndDateAt(?\DateTimeImmutable $endDateAt): static
    {
        $this->endDateAt = $endDateAt;

        return $this;
    }

    public function getProductType(): ?string
    {
        return $this->productType;
    }

    public function setProductType(?string $productType): static
    {
        $this->productType = $productType;

        return $this;
    }

    public function isMobileOrInternetService(): bool
    {
        return $this->isMobileOrInternetService;
    }

    public function setIsMobileOrInternetService(bool $isMobileOrInternetService): static
    {
        $this->isMobileOrInternetService = $isMobileOrInternetService;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBenefits(): array
    {
        return $this->benefits;
    }

    /**
     * @param array<int, array<string, mixed>> $benefits
     */
    public function setBenefits(array $benefits): static
    {
        $this->benefits = $benefits;

        return $this;
    }

    /**
     * @return array{name?: string, subservice?: array{name?: string}}
     */
    public function getService(): array
    {
        return $this->service;
    }

    /**
     * @param array{name?: string, subservice?: array{name?: string}} $service
     */
    public function setService(array $service): static
    {
        $this->service = $service;

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

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): static
    {
        $this->environment = $environment;

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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalRef(): string
    {
        return $this->externalRef;
    }

    public function setExternalRef(string $externalRef): static
    {
        $this->externalRef = $externalRef;

        return $this;
    }

    public function getDestinationAmount(): ?float
    {
        return $this->destinationAmount;
    }

    public function setDestinationAmount(?float $destinationAmount): static
    {
        $this->destinationAmount = $destinationAmount;

        return $this;
    }

    public function getDestinationUnit(): ?string
    {
        return $this->destinationUnit;
    }

    public function setDestinationUnit(?string $destinationUnit): static
    {
        $this->destinationUnit = $destinationUnit;

        return $this;
    }

    public function getPriceCurrency(): ?string
    {
        return $this->priceCurrency;
    }

    public function setPriceCurrency(?string $priceCurrency): static
    {
        $this->priceCurrency = $priceCurrency;

        return $this;
    }
}
