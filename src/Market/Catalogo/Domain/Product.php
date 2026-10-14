<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Producto del mercado (COMR-273): la entidad propia, sin pasar por CommunicationPackage. El sku y el vendedor se fijan al
 * crear y no cambian; el resto (ProductSpec) se edita. Pasa por tres estados: borrador, publicado y retirado.
 *
 * Las cantidades se guardan en columnas: el valor en la unidad base (g, ml, unidades), el original y su unidad, para
 * mostrarlas como las introdujo el vendedor. Los totales derivados (peso y volumen) también se guardan, para poder
 * ordenar y calcular la relación precio/peso (COMR-165) sin recalcular. Marca y categorías llegan con COMR-70.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_product')]
#[ORM\UniqueConstraint(name: 'uniq_market_product_sku', columns: ['sku'])]
#[ORM\Index(name: 'idx_market_product_seller', columns: ['seller_id'])]
#[ORM\Index(name: 'idx_market_product_status', columns: ['status'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $sku;

    #[ORM\Column(name: 'seller_id', type: 'integer')]
    private int $sellerId;

    #[ORM\Column(type: 'string', length: 12)]
    private string $status;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $area = null;

    /** @var array<string, array{name: string, description: string|null}> */
    #[ORM\Column(type: 'json')]
    private array $texts = [];

    #[ORM\Column(name: 'content_base', type: 'decimal', precision: 14, scale: 3)]
    private string $contentBase;

    #[ORM\Column(name: 'content_original', type: 'decimal', precision: 14, scale: 4)]
    private string $contentOriginal;

    #[ORM\Column(name: 'content_unit', type: 'string', length: 8)]
    private string $contentUnit;

    #[ORM\Column(name: 'unit_weight_base', type: 'decimal', precision: 14, scale: 3, nullable: true)]
    private ?string $unitWeightBase = null;

    #[ORM\Column(name: 'unit_weight_original', type: 'decimal', precision: 14, scale: 4, nullable: true)]
    private ?string $unitWeightOriginal = null;

    #[ORM\Column(name: 'unit_weight_unit', type: 'string', length: 8, nullable: true)]
    private ?string $unitWeightUnit = null;

    #[ORM\Column(name: 'unit_volume_base', type: 'decimal', precision: 14, scale: 3, nullable: true)]
    private ?string $unitVolumeBase = null;

    #[ORM\Column(name: 'unit_volume_original', type: 'decimal', precision: 14, scale: 4, nullable: true)]
    private ?string $unitVolumeOriginal = null;

    #[ORM\Column(name: 'unit_volume_unit', type: 'string', length: 8, nullable: true)]
    private ?string $unitVolumeUnit = null;

    #[ORM\Column(name: 'length_mm', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $lengthMm = null;

    #[ORM\Column(name: 'width_mm', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $widthMm = null;

    #[ORM\Column(name: 'height_mm', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $heightMm = null;

    #[ORM\Column(name: 'delivery_kind', type: 'string', length: 8)]
    private string $deliveryKind;

    #[ORM\Column(name: 'delivery_unit', type: 'string', length: 8)]
    private string $deliveryUnit;

    #[ORM\Column(type: 'string', length: 14, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(name: 'total_weight_g', type: 'decimal', precision: 16, scale: 3, nullable: true)]
    private ?string $totalWeightG = null;

    #[ORM\Column(name: 'total_volume_ml', type: 'decimal', precision: 16, scale: 3, nullable: true)]
    private ?string $totalVolumeMl = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(Sku $sku, int $sellerId, ProductSpec $spec)
    {
        $this->sku = $sku->value();
        $this->sellerId = $sellerId;
        $this->status = ProductStatus::DRAFT->value;
        $this->apply($spec);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(Sku $sku, int $sellerId, ProductSpec $spec): self
    {
        return new self($sku, $sellerId, $spec);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sku(): Sku
    {
        return new Sku($this->sku);
    }

    public function sellerId(): int
    {
        return $this->sellerId;
    }

    public function status(): ProductStatus
    {
        return ProductStatus::from($this->status);
    }

    public function isPublished(): bool
    {
        return $this->status() === ProductStatus::PUBLISHED;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function totalWeightG(): ?float
    {
        return $this->totalWeightG === null ? null : (float) $this->totalWeightG;
    }

    public function totalVolumeMl(): ?float
    {
        return $this->totalVolumeMl === null ? null : (float) $this->totalVolumeMl;
    }

    public function spec(): ProductSpec
    {
        return new ProductSpec(
            ProductTexts::fromArray($this->texts),
            $this->quantity($this->contentBase, $this->contentOriginal, $this->contentUnit),
            $this->unitWeightBase === null ? null : $this->quantity($this->unitWeightBase, (string) $this->unitWeightOriginal, (string) $this->unitWeightUnit),
            $this->unitVolumeBase === null ? null : $this->quantity($this->unitVolumeBase, (string) $this->unitVolumeOriginal, (string) $this->unitVolumeUnit),
            $this->lengthMm === null ? null : Dimensions::of((float) $this->lengthMm, (float) $this->widthMm, (float) $this->heightMm),
            DeliveryBasis::of(MeasureKind::from($this->deliveryKind), MeasureUnit::from($this->deliveryUnit)),
            $this->area === null ? null : ProductArea::from($this->area),
            $this->barcode,
        );
    }

    /** El sku y el vendedor no se tocan. */
    public function update(ProductSpec $spec): void
    {
        $this->apply($spec);
        $this->touch();
    }

    /**
     * @throws InvalidProductTransition
     */
    public function publish(): void
    {
        $this->transitionTo(ProductStatus::PUBLISHED, [ProductStatus::DRAFT, ProductStatus::WITHDRAWN]);
    }

    /**
     * @throws InvalidProductTransition solo se retira un producto publicado
     */
    public function withdraw(): void
    {
        $this->transitionTo(ProductStatus::WITHDRAWN, [ProductStatus::PUBLISHED]);
    }

    /**
     * @param list<ProductStatus> $from
     */
    private function transitionTo(ProductStatus $to, array $from): void
    {
        $current = $this->status();
        if ($current === $to) {
            return;
        }
        if (!in_array($current, $from, true)) {
            throw InvalidProductTransition::from($current, $to);
        }
        $this->status = $to->value;
        $this->touch();
    }

    private function apply(ProductSpec $spec): void
    {
        $this->texts = $spec->texts->all();
        $this->area = $spec->area?->value;
        $this->barcode = $spec->barcode;

        $this->contentBase = self::decimal($spec->content->baseValue(), 3);
        $this->contentOriginal = self::decimal($spec->content->originalValue(), 4);
        $this->contentUnit = $spec->content->unit()->value;

        $this->unitWeightBase = $spec->unitWeight === null ? null : self::decimal($spec->unitWeight->baseValue(), 3);
        $this->unitWeightOriginal = $spec->unitWeight === null ? null : self::decimal($spec->unitWeight->originalValue(), 4);
        $this->unitWeightUnit = $spec->unitWeight?->unit()->value;
        $this->unitVolumeBase = $spec->unitVolume === null ? null : self::decimal($spec->unitVolume->baseValue(), 3);
        $this->unitVolumeOriginal = $spec->unitVolume === null ? null : self::decimal($spec->unitVolume->originalValue(), 4);
        $this->unitVolumeUnit = $spec->unitVolume?->unit()->value;

        $this->lengthMm = $spec->dimensions === null ? null : self::decimal($spec->dimensions->lengthMm, 2);
        $this->widthMm = $spec->dimensions === null ? null : self::decimal($spec->dimensions->widthMm, 2);
        $this->heightMm = $spec->dimensions === null ? null : self::decimal($spec->dimensions->heightMm, 2);

        $this->deliveryKind = $spec->deliveryBasis->kind()->value;
        $this->deliveryUnit = $spec->deliveryBasis->unit()->value;

        $weight = $spec->totalWeightG();
        $volume = $spec->totalVolumeMl();
        $this->totalWeightG = $weight === null ? null : self::decimal($weight, 3);
        $this->totalVolumeMl = $volume === null ? null : self::decimal($volume, 3);
    }

    private function quantity(string $base, string $original, string $unit): Quantity
    {
        return Quantity::fromStored((float) $base, (float) $original, MeasureUnit::from($unit));
    }

    private static function decimal(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
