<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * La consulta que Catálogo ofrece a Distribución para cotizar la entrega: los datos físicos de un producto ya convertidos a
 * kilogramos y litros, sea cual sea la unidad base configurada.
 */
final class GetProductDeliveryFacts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly MeasureSettings $settings,
    ) {
    }

    public function __invoke(int $id): ?ProductDeliveryFacts
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            return null;
        }
        $spec = $product->spec();
        $system = MeasureSystem::fromSettings($this->settings);
        $name = $spec->texts->forLanguage('es')['es']['name'] ?? $product->sku()->value();
        $divisor = $this->settings->volumetricDivisor();

        if ($spec->isDigital() || $spec->deliveryBasis === null) {
            return new ProductDeliveryFacts((int) $product->id(), $name, null, null, null, null, $divisor);
        }

        $weight = $product->totalWeight();
        $volume = $product->totalVolume();

        return new ProductDeliveryFacts(
            (int) $product->id(),
            $name,
            $spec->deliveryBasis->kind() === MeasureKind::WEIGHT ? 'weight' : 'volume',
            $weight === null ? null : round($weight / $system->factorOf(MeasureUnit::KG), 6),
            $volume === null ? null : round($volume / $system->factorOf(MeasureUnit::L), 6),
            $spec->dimensions?->volumeCm3(),
            $divisor,
        );
    }
}
