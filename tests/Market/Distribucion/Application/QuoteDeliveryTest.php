<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Application\CoverageInput;
use App\Market\Distribucion\Application\FindServingWarehouse;
use App\Market\Distribucion\Application\QuoteDelivery;
use App\Market\Distribucion\Application\QuoteLineInput;
use App\Market\Distribucion\Application\SetCoverage;
use App\Market\Distribucion\Domain\DeliveryProduct;
use App\Market\Distribucion\Domain\InvalidQuote;
use App\Market\Distribucion\Domain\QuoteMunicipalityInvalid;
use App\Market\Distribucion\Domain\QuoteNoCoverage;
use App\Market\Distribucion\Domain\QuoteProductInvalid;
use App\Market\Distribucion\Domain\QuoteWarehouseNotActive;
use PHPUnit\Framework\TestCase;

final class QuoteDeliveryTest extends TestCase
{
    private InMemoryCoverageRepository $coverage;
    private InMemoryDeliveryCurrencies $currencies;
    private FakeDeliveryWarehouses $warehouses;
    private FakeDeliveryMunicipalities $municipalities;
    private FakeDeliveryProducts $products;

    protected function setUp(): void
    {
        $this->coverage = new InMemoryCoverageRepository();
        $this->currencies = new InMemoryDeliveryCurrencies();
        $this->warehouses = new FakeDeliveryWarehouses();
        $this->warehouses->add(1, 'Principal');
        $this->warehouses->add(2, 'Socio');
        $this->municipalities = new FakeDeliveryMunicipalities();
        $this->municipalities->add(10, 'Boyeros');
        $this->municipalities->add(12, 'Cerro', selectable: false);
        $this->products = new FakeDeliveryProducts();
        // Un producto por peso de 2,4 kg con caja de 400 × 400 × 300 mm: 48 000 cm³ ÷ 6000 = 8 kg tarifables.
        $this->products->add(new DeliveryProduct(100, 'Caja grande', 'weight', 2_400_000, null, '48000', 6000));
        $this->products->add(new DeliveryProduct(101, 'Arroz 1 kg', 'weight', 1_000_000, null, null, 6000));
        $this->products->add(new DeliveryProduct(102, 'Aceite 1 L', 'volume', null, 1_000_000, null, 6000));
        $this->products->add(new DeliveryProduct(103, 'Gift card', null, null, null, null, 6000));
        $this->cover(1, 10);
    }

    private function cover(int $warehouse, int $municipality, string $perKg = '0.60', string $perL = '0.45', string $min = '2.50', ?string $max = '12.00', int $priority = 1): void
    {
        (new SetCoverage($this->coverage, $this->warehouses, $this->municipalities, $this->currencies))($warehouse, $municipality, new CoverageInput($perKg, $perL, $min, $max, $priority, false, true));
    }

    private function quote(): QuoteDelivery
    {
        return new QuoteDelivery(new FindServingWarehouse($this->coverage, $this->warehouses), $this->coverage, $this->municipalities, $this->products, $this->currencies);
    }

    public function testAProductByWeightWithABoxChargesTheVolumetricWeight(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::product(100, 1)]);

        $this->assertSame('8.000000', $quote->lines[0]->billable);
        $this->assertSame('2.400000', $quote->lines[0]->realKg);
        $this->assertSame('8.000000', $quote->lines[0]->volumetricKg);
        $this->assertSame('4.80', $quote->lines[0]->amount);
        $this->assertSame('4.80', $quote->total);
        $this->assertSame('none', $quote->limit);
    }

    public function testTheRealWeightWinsWhenItIsGreaterThanTheVolumetricOne(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::product(101, 5)]);

        $this->assertSame('5.000000', $quote->lines[0]->billable);
        $this->assertNull($quote->lines[0]->volumetricKg);
        $this->assertSame('3.00', $quote->total);
    }

    public function testAVolumeProductChargesItsLitersAtTheLiterRate(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::product(102, 40)]);

        $this->assertSame('40.000000', $quote->lines[0]->billable);
        $this->assertSame('0.4500', $quote->lines[0]->rate);
        $this->assertSame('12.00', $quote->total);
        $this->assertSame('max', $quote->limit);
    }

    public function testAMixedOrderSumsBothPartsAndAppliesTheLimitsOnce(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::product(101, 12), QuoteLineInput::product(102, 10)]);

        $this->assertSame('7.20', $quote->kgAmount);
        $this->assertSame('4.50', $quote->litersAmount);
        $this->assertSame('11.70', $quote->subtotal);
        $this->assertSame('11.70', $quote->total);
    }

    public function testThePreviewAcceptsRawKgAndLiters(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::kg('12'), QuoteLineInput::liters('10')]);

        $this->assertSame('11.70', $quote->total);
        $this->assertNull($quote->lines[0]->productId);
    }

    public function testTheQuoteCarriesWhatIsNeededToFreezeItInTheSale(): void
    {
        $quote = ($this->quote())(10, [QuoteLineInput::kg('5')]);

        $this->assertSame(1, $quote->warehouseId);
        $this->assertNotNull($quote->coverageId);
        $this->assertSame('EUR', $quote->currency);
        $this->assertSame(['perKg' => '0.6000', 'perL' => '0.4500', 'min' => '2.5000', 'max' => '12.0000'], $quote->rates);
        $this->assertSame('linear', $quote->rateMode);
    }

    public function testTheCurrencyIsTheServingWarehouses(): void
    {
        $this->currencies->set(1, 'USD');

        $this->assertSame('USD', ($this->quote())(10, [QuoteLineInput::kg('5')])->currency);
    }

    public function testTheLowestPriorityWarehouseQuotesWithItsOwnRates(): void
    {
        $this->cover(2, 10, perKg: '0.20', priority: 0 + 1);
        $this->cover(1, 10, perKg: '0.60', priority: 2);

        $quote = ($this->quote())(10, [QuoteLineInput::kg('10')]);

        $this->assertSame(2, $quote->warehouseId);
        $this->assertSame('2.50', $quote->total, '10 kg × 0,20 = 2,00 → sube al mínimo');
    }

    public function testAMunicipalityWithoutCoverageIsRejected(): void
    {
        $this->municipalities->add(11, 'Playa');

        $this->expectException(QuoteNoCoverage::class);
        ($this->quote())(11, [QuoteLineInput::kg('5')]);
    }

    public function testAnInactiveMunicipalityIsNotDeliverable(): void
    {
        $this->expectException(QuoteMunicipalityInvalid::class);
        ($this->quote())(12, [QuoteLineInput::kg('5')]);
    }

    public function testAnUnknownMunicipalityIsRejected(): void
    {
        $this->expectException(QuoteMunicipalityInvalid::class);
        ($this->quote())(99, [QuoteLineInput::kg('5')]);
    }

    public function testACoverageOnlyInAnInactiveWarehouseSaysTheWarehouseIsNotActive(): void
    {
        $this->warehouses->add(1, 'Principal', 'inactive');

        $this->expectException(QuoteWarehouseNotActive::class);
        ($this->quote())(10, [QuoteLineInput::kg('5')]);
    }

    public function testADigitalOrUnknownProductCannotBeQuoted(): void
    {
        foreach ([103, 999] as $id) {
            try {
                ($this->quote())(10, [QuoteLineInput::product($id, 1)]);
                $this->fail('debía rechazar el producto ' . $id);
            } catch (QuoteProductInvalid) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAProductWhoseBasisCannotBeComputedIsRejected(): void
    {
        $this->products->add(new DeliveryProduct(104, 'Sin peso', 'weight', null, null, null, 6000));

        $this->expectException(QuoteProductInvalid::class);
        ($this->quote())(10, [QuoteLineInput::product(104, 1)]);
    }

    public function testEmptyOrInvalidLinesAreRejected(): void
    {
        foreach ([[], [QuoteLineInput::product(101, 0)], [QuoteLineInput::kg('abc')], [QuoteLineInput::kg('-2')], [QuoteLineInput::product(101, 100001)]] as $lines) {
            try {
                ($this->quote())(10, $lines);
                $this->fail('debía rechazar las líneas');
            } catch (InvalidQuote) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
