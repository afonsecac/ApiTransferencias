<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Entity\CommunicationPackage;
use App\Market\Catalogo\Application\ProductDetails;
use App\Market\Catalogo\Application\ProductDetailsAssembler;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Infrastructure\Storage\ImageUrls;
use App\Service\Catalog\MarketPackageEnricher;

/**
 * Los datos propios del mercado de un paquete del catálogo del comprador (COMR-275), leídos en vivo del producto: contenido,
 * peso (en g, kg y lb) y precio por libra, volumen, dimensiones, marca, categorías, textos por idioma, imágenes con sus URLs y la
 * relevancia mientras está en vigor. Un dato sin valor no aparece. Los paquetes de comunicaciones no se tocan.
 */
final class MarketPackageFacts implements MarketPackageEnricher
{

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDetailsAssembler $assembler,
        private readonly ProductImageRepository $images,
        private readonly ImageUrls $urls,
        private readonly MeasureSettings $measureSettings,
    ) {
    }

    public function enrich(iterable $packages, \DateTimeImmutable $now): void
    {
        $market = [];
        foreach ($packages as $package) {
            if ($package->getMarketProductId() !== null) {
                $market[$package->getMarketProductId()][] = $package;
            }
        }
        if ($market === []) {
            return;
        }

        $ids = array_keys($market);
        $details = [];
        foreach ($this->assembler->many($this->products->findByIds($ids)) as $one) {
            $details[(int) $one->product->id()] = $one;
        }
        $images = $this->images->findByProducts($ids);

        foreach ($market as $productId => $list) {
            if (!isset($details[$productId])) {
                continue;
            }
            foreach ($list as $package) {
                $package->setMarket($this->facts($details[$productId], $images[$productId] ?? [], $package->getAmount(), $now));
            }
        }
    }

    /**
     * @param list<ProductImage> $images
     *
     * @return array<string, mixed>
     */
    private function facts(ProductDetails $details, array $images, ?float $price, \DateTimeImmutable $now): array
    {
        $product = $details->product;
        $spec = $product->spec();
        $measures = MeasureSystem::fromSettings($this->measureSettings);
        // El contrato público no cambia: se entrega en g, kg y lb y en ml, sea cual sea la base con que se guarda.
        $weightKg = $product->totalWeight() === null ? null : $product->totalWeight() * $measures->weightBase->factor();
        $volumeMl = $product->totalVolume() === null ? null : $product->totalVolume() * $measures->volumeBase->factor() / MeasureUnit::ML->factor();
        $facts = [
            'sku' => $product->sku()->value(),
            'texts' => $this->texts($spec->texts->all()),
            'content' => $spec->content === null ? null : $this->quantity($spec->content),
            'unitWeight' => $spec->unitWeight === null ? null : $this->quantity($spec->unitWeight),
            'unitVolume' => $spec->unitVolume === null ? null : $this->quantity($spec->unitVolume),
            'weight' => $weightKg === null ? null : [
                'g' => round($weightKg / MeasureUnit::G->factor(), 3),
                'kg' => round($weightKg, 3),
                'lb' => round($weightKg / MeasureUnit::LB->factor(), 3),
            ],
            'pricePerLb' => $weightKg === null || $price === null || $weightKg <= 0 ? null : round($price / ($weightKg / MeasureUnit::LB->factor()), 2),
            'volumeMl' => $volumeMl === null ? null : round($volumeMl, 3),
            'dimensions' => $spec->dimensions === null ? null : [
                'lengthMm' => $spec->dimensions->lengthMm,
                'widthMm' => $spec->dimensions->widthMm,
                'heightMm' => $spec->dimensions->heightMm,
            ],
            'barcode' => $spec->barcode,
            'brand' => $details->brand?->name(),
            'categories' => array_values($details->categoryPaths),
            'attributes' => $spec->attributes->all(),
            'images' => array_map(fn (ProductImage $image): array => $this->image($image), $images),
            'relevance' => $this->relevance($product->relevance(), $now),
        ];
        $facts['volume'] = $facts['volumeMl'];
        unset($facts['volumeMl']);

        return array_filter($facts, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array{value: float, unit: string}
     */
    private function quantity(Quantity $quantity): array
    {
        return ['value' => $quantity->originalValue(), 'unit' => $quantity->unit()->value];
    }

    /**
     * @param array<string, array{name: string, description: string|null}> $texts
     *
     * @return array<string, array<string, string>>
     */
    private function texts(array $texts): array
    {
        $out = [];
        foreach ($texts as $language => $text) {
            $out[$language] = array_filter(['name' => $text['name'], 'description' => $text['description']], static fn (?string $v): bool => $v !== null && $v !== '');
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function image(ProductImage $image): array
    {
        return array_filter([
            'urls' => $this->urls->for($image->file()),
            'kind' => $image->kind(),
            'alt' => array_filter(['es' => $image->altEs(), 'en' => $image->altEn()], static fn (?string $v): bool => $v !== null && $v !== ''),
            'isReference' => $image->isReference() ? true : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array{level: int, from?: string, until?: string}|null solo mientras la marca está en vigor
     */
    private function relevance(\App\Market\Catalogo\Domain\Relevance $relevance, \DateTimeImmutable $now): ?array
    {
        if (!$relevance->isActiveOn($now)) {
            return null;
        }

        return array_filter([
            'level' => $relevance->level(),
            'from' => $relevance->from()?->format('Y-m-d'),
            'until' => $relevance->until()?->format('Y-m-d'),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
