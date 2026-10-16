<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\BillableLoad;
use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryPricing;
use App\Market\Distribucion\Domain\DeliveryProduct;
use App\Market\Distribucion\Domain\DeliveryProducts;
use App\Market\Distribucion\Domain\InvalidQuote;
use App\Market\Distribucion\Domain\QuoteMunicipalityInvalid;
use App\Market\Distribucion\Domain\QuoteNoCoverage;
use App\Market\Distribucion\Domain\QuoteProductInvalid;
use App\Market\Distribucion\Domain\QuoteWarehouseNotActive;

/**
 * Cotiza la entrega de un pedido en un municipio (COMR-73) con las tarifas del almacén que lo atiende. Cada línea aporta kg (si es
 * por peso: el mayor entre el peso real y el volumétrico, largo × ancho × alto ÷ el divisor) o L (si es por volumen); el mínimo y
 * el máximo se aplican una sola vez sobre la suma. Solo un almacén activo cotiza.
 */
final class QuoteDelivery
{
    private const MAX_QUANTITY = 100_000;

    public function __construct(
        private readonly FindServingWarehouse $serving,
        private readonly CoverageRepository $coverage,
        private readonly DeliveryMunicipalities $municipalities,
        private readonly DeliveryProducts $products,
        private readonly DeliveryCurrencies $currencies,
    ) {
    }

    /**
     * @param list<QuoteLineInput> $lines
     *
     * @throws InvalidQuote
     * @throws QuoteMunicipalityInvalid
     * @throws QuoteNoCoverage
     * @throws QuoteWarehouseNotActive
     * @throws QuoteProductInvalid
     */
    public function __invoke(int $municipalityId, array $lines): Quote
    {
        if ($lines === []) {
            throw new InvalidQuote('Una cotización necesita al menos una línea.');
        }
        $municipality = $this->municipalities->find($municipalityId) ?? throw QuoteMunicipalityInvalid::unknown($municipalityId);
        if (!$municipality->selectable) {
            throw QuoteMunicipalityInvalid::notDeliverable($municipality->name);
        }

        $serving = ($this->serving)($municipalityId);
        if ($serving === null) {
            throw $this->coverage->activeInMunicipality($municipalityId) === []
                ? QuoteNoCoverage::in($municipality->name)
                : QuoteWarehouseNotActive::in($municipality->name);
        }
        $rates = $serving->coverage->rates();

        $loads = $labels = [];
        foreach ($lines as $line) {
            [$load, $label, $productId, $quantity] = $this->load($line);
            $loads[] = $load;
            $labels[] = [$label, $productId, $quantity];
        }
        $price = DeliveryPricing::price($rates, $loads);

        $quoted = [];
        foreach ($price->lines as $i => $priced) {
            [$label, $productId, $quantity] = $labels[$i];
            $quoted[] = new QuotedLine(
                $productId,
                $label,
                $priced->load->kind,
                $quantity,
                $priced->load->realValue(),
                $priced->load->volumetricValue(),
                $priced->load->value(),
                $priced->rate->value(),
                $priced->amount(),
            );
        }

        return new Quote(
            $municipalityId,
            $serving->coverage->warehouseId(),
            $serving->coverage->id(),
            $this->currencies->currencyOf($serving->coverage->warehouseId()),
            $serving->coverage->rateMode(),
            ['perKg' => $rates->perKg->value(), 'perL' => $rates->perL->value(), 'min' => $rates->min->value(), 'max' => $rates->max?->value()],
            $quoted,
            $price->kgAmount(),
            $price->litersAmount(),
            $price->subtotal(),
            $price->limit,
            $price->total(),
        );
    }

    /** @return array{BillableLoad, string, ?int, ?int} */
    private function load(QuoteLineInput $line): array
    {
        if ($line->kg !== null) {
            return [BillableLoad::of(BillableLoad::WEIGHT, $line->kg), $line->kg . ' kg', null, null];
        }
        if ($line->liters !== null) {
            return [BillableLoad::of(BillableLoad::VOLUME, $line->liters), $line->liters . ' L', null, null];
        }

        $quantity = (int) $line->quantity;
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidQuote(sprintf('La cantidad debe estar entre 1 y %d.', self::MAX_QUANTITY));
        }
        $product = $this->products->find((int) $line->productId) ?? throw QuoteProductInvalid::unknown((int) $line->productId);

        return [$this->productLoad($product, $quantity), $product->name, $product->id, $quantity];
    }

    private function productLoad(DeliveryProduct $product, int $quantity): BillableLoad
    {
        if ($product->basis === BillableLoad::VOLUME && $product->volumeMicroL !== null) {
            return BillableLoad::fromMicro(BillableLoad::VOLUME, $product->volumeMicroL * $quantity);
        }
        if ($product->basis === BillableLoad::WEIGHT && $product->weightMicroKg !== null) {
            $real = $product->weightMicroKg;
            // Peso volumétrico de una unidad: cm³ ÷ divisor (cm³ por kg), en millonésimas de kg.
            $volumetric = $product->volumeCm3 === null || $product->volumetricDivisor <= 0
                ? null
                : (int) round((float) $product->volumeCm3 * 1_000_000 / $product->volumetricDivisor);

            return BillableLoad::fromMicro(BillableLoad::WEIGHT, max($real, $volumetric ?? 0) * $quantity, $real * $quantity, $volumetric === null ? null : $volumetric * $quantity);
        }

        throw QuoteProductInvalid::notDeliverable($product->name);
    }
}
