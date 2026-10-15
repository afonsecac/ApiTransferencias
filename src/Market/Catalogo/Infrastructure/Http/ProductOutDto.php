<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\ProductDetails;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Infrastructure\Storage\ImageUrls;

/**
 * Respuesta de los endpoints de productos (estructura de presentación, no es de entrada). Cada cantidad lleva su valor
 * original con su unidad (para mostrarla y editarla) y su valor en la unidad base (g, ml, unidades).
 */
class ProductOutDto
{
    /**
     * @param array{id: int, slug: string, name: string, isPlatform: bool}|null $seller
     * @param array<string, array{name: string, description: string|null}> $texts
     * @param array<string, mixed>|null $content null en un producto con valor
     * @param array<string, mixed>|null $unitWeight
     * @param array<string, mixed>|null $unitVolume
     * @param array<string, float>|null $dimensions
     * @param array{kind: string, unit: string}|null $deliveryBasis null en un producto con valor
     * @param array{kind: string, currency: string, min: string, max: string|null}|null $value solo en un producto con valor (digital)
     * @param array{id: int, slug: string, name: string, isActive: bool}|null $brand
     * @param array{level: int, from: string|null, until: string|null, active: bool} $relevance active = en vigor hoy
     * @param list<array{id: int, slug: string, nameEs: string, nameEn: string|null, path: string, isActive: bool}> $categories
     * @param array<string, string>|null $image las URLs por tamaño (thumb, list, detail) de la imagen de referencia; null si no tiene
     */
    public function __construct(
        public int $id,
        public string $sku,
        public string $status,
        public string $service,
        public string $subservice,
        public ?string $area,
        public ?array $seller,
        public array $texts,
        public ?array $content,
        public ?array $unitWeight,
        public ?array $unitVolume,
        public ?array $dimensions,
        public ?array $deliveryBasis,
        public ?array $value,
        public ?string $barcode,
        public ?array $brand,
        public array $categories,
        public array $relevance,
        public ?float $totalWeightG,
        public ?float $totalVolumeMl,
        public string $createdAt,
        public string $updatedAt,
        public ?array $image = null,
    ) {
    }

    /**
     * @param string|null $language con idioma, solo los textos de ese idioma (o el español si no hay traducción); sin él, todos
     */
    public static function from(ProductDetails $details, ?string $language = null, ?ImageUrls $urls = null): self
    {
        $product = $details->product;
        $spec = $product->spec();
        $seller = $details->seller;

        return new self(
            (int) $product->id(),
            $product->sku()->value(),
            $product->status()->value,
            $spec->classification->service(),
            $spec->classification->subservice(),
            $spec->classification->area(),
            $seller === null ? null : ['id' => (int) $seller->id(), 'slug' => $seller->slug()->value(), 'name' => $seller->name(), 'isPlatform' => $seller->isPlatform()],
            $spec->texts->forLanguage($language),
            $spec->content === null ? null : self::quantity($spec->content),
            $spec->unitWeight === null ? null : self::quantity($spec->unitWeight),
            $spec->unitVolume === null ? null : self::quantity($spec->unitVolume),
            $spec->dimensions === null ? null : [
                'lengthMm' => $spec->dimensions->lengthMm,
                'widthMm' => $spec->dimensions->widthMm,
                'heightMm' => $spec->dimensions->heightMm,
                'volumeCm3' => $spec->dimensions->volumeCm3(),
            ],
            $spec->deliveryBasis === null ? null : ['kind' => $spec->deliveryBasis->kind()->value, 'unit' => $spec->deliveryBasis->unit()->value],
            $spec->value === null ? null : [
                'kind' => $spec->value->isFixed() ? 'fixed' : 'ranged',
                'currency' => $spec->value->currency(),
                'min' => $spec->value->minAmount()->toString(),
                'max' => $spec->value->maxAmount()?->toString(),
            ],
            $spec->barcode,
            $details->brand === null ? null : [
                'id' => (int) $details->brand->id(),
                'slug' => $details->brand->slug()->value(),
                'name' => $details->brand->name(),
                'isActive' => $details->brand->isActive(),
            ],
            array_map(static fn ($category): array => [
                'id' => (int) $category->id(),
                'slug' => $category->slug()->value(),
                'nameEs' => $category->nameEs(),
                'nameEn' => $category->nameEn(),
                'path' => $details->categoryPaths[(int) $category->id()] ?? $category->nameEs(),
                'isActive' => $category->isActive(),
            ], $details->categories),
            [
                'level' => $product->relevance()->level(),
                'from' => $product->relevance()->from()?->format('Y-m-d'),
                'until' => $product->relevance()->until()?->format('Y-m-d'),
                'active' => $product->isRelevantOn(new \DateTimeImmutable('today')),
            ],
            $product->totalWeightG(),
            $product->totalVolumeMl(),
            $product->createdAt()->format(DATE_ATOM),
            $product->updatedAt()->format(DATE_ATOM),
            $urls === null || $details->referenceImage === null ? null : $urls->for($details->referenceImage->file()),
        );
    }

    /**
     * @return array{value: float, unit: string, base: float, baseUnit: string, kind: string}
     */
    private static function quantity(Quantity $quantity): array
    {
        return [
            'value' => $quantity->originalValue(),
            'unit' => $quantity->unit()->value,
            'base' => $quantity->baseValue(),
            'baseUnit' => $quantity->kind()->baseUnit()->value,
            'kind' => $quantity->kind()->value,
        ];
    }
}
