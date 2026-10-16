<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Application\BulkCoverageByProvince;
use App\Market\Distribucion\Application\CoverageInput;
use App\Market\Distribucion\Application\FindServingWarehouse;
use App\Market\Distribucion\Application\ListCoverage;
use App\Market\Distribucion\Application\SetCoverage;
use App\Market\Distribucion\Application\SetCoverageActive;
use App\Market\Distribucion\Application\SetDeliveryCurrency;
use App\Market\Distribucion\Domain\CoverageConfirmationRequired;
use App\Market\Distribucion\Domain\CoverageMunicipalityInvalid;
use App\Market\Distribucion\Domain\CoverageNotFound;
use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\InvalidCoverage;
use PHPUnit\Framework\TestCase;

final class CoverageUseCasesTest extends TestCase
{
    private InMemoryCoverageRepository $coverage;
    private InMemoryDeliveryCurrencies $currencies;
    private FakeDeliveryWarehouses $warehouses;
    private FakeDeliveryMunicipalities $municipalities;

    protected function setUp(): void
    {
        $this->coverage = new InMemoryCoverageRepository();
        $this->currencies = new InMemoryDeliveryCurrencies();
        $this->warehouses = new FakeDeliveryWarehouses();
        $this->warehouses->add(1, 'Principal');
        $this->warehouses->add(2, 'Socio');
        $this->warehouses->add(3, 'Borrador', 'draft');
        $this->warehouses->add(4, 'Inactivo', 'inactive');
        $this->municipalities = new FakeDeliveryMunicipalities();
        $this->municipalities->add(10, 'Boyeros');
        $this->municipalities->add(11, 'Playa');
        $this->municipalities->add(12, 'Cerro', selectable: false);
        $this->municipalities->add(20, 'Matanzas', 2, 'Matanzas');
    }

    private function input(string $perKg = '0.35', string $perL = '0.40', string $min = '2', ?string $max = '12', int $priority = 1, bool $zero = false, bool $noMax = false): CoverageInput
    {
        return new CoverageInput($perKg, $perL, $min, $max, $priority, $zero, $noMax);
    }

    private function set(): SetCoverage
    {
        return new SetCoverage($this->coverage, $this->warehouses, $this->municipalities, $this->currencies);
    }

    public function testItCreatesTheCoverageOfAnActiveWarehouseInASelectableMunicipality(): void
    {
        $row = ($this->set())(1, 10, $this->input());

        $this->assertSame('0.3500', $row->coverage->rates()->perKg->value());
        $this->assertTrue($row->coverage->isActive());
        $this->assertSame('Boyeros', $row->municipality?->name);
        $this->assertSame('EUR', $row->currency);
    }

    public function testRepeatingItUpdatesTheSameRow(): void
    {
        ($this->set())(1, 10, $this->input());
        $row = ($this->set())(1, 10, $this->input(perKg: '0.50', priority: 2));

        $this->assertCount(1, $this->coverage->ofWarehouse(1));
        $this->assertSame('0.5000', $row->coverage->rates()->perKg->value());
        $this->assertSame(2, $row->coverage->priority());
    }

    public function testADraftWarehouseAcceptsNoCoverage(): void
    {
        $this->expectException(CoverageWarehouseInvalid::class);
        ($this->set())(3, 10, $this->input());
    }

    public function testAnInactiveWarehouseCannotStartNewCoverageButKeepsItsExistingOne(): void
    {
        $this->warehouses->add(5, 'Va a desactivarse');
        ($this->set())(5, 10, $this->input());
        $this->warehouses->add(5, 'Va a desactivarse', 'inactive');

        try {
            ($this->set())(5, 11, $this->input());
            $this->fail('debía rechazar la alta nueva');
        } catch (CoverageWarehouseInvalid) {
            $this->addToAssertionCount(1);
        }
        $row = ($this->set())(5, 10, $this->input(perKg: '0.60'));
        $this->assertSame('0.6000', $row->coverage->rates()->perKg->value());
    }

    public function testAnUnknownWarehouseOrMunicipalityIsRejected(): void
    {
        try {
            ($this->set())(99, 10, $this->input());
            $this->fail();
        } catch (CoverageWarehouseInvalid) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(CoverageMunicipalityInvalid::class);
        ($this->set())(1, 99, $this->input());
    }

    public function testAnInactiveMunicipalityCannotStartCoverage(): void
    {
        $this->expectException(CoverageMunicipalityInvalid::class);
        ($this->set())(1, 12, $this->input());
    }

    public function testAMunicipalityDeactivatedLaterKeepsItsCoverageEditable(): void
    {
        ($this->set())(1, 10, $this->input());
        $this->municipalities->add(10, 'Boyeros', selectable: false);

        $row = ($this->set())(1, 10, $this->input(perKg: '0.45'));

        $this->assertSame('0.4500', $row->coverage->rates()->perKg->value());
        $this->assertFalse($row->municipality?->selectable);
    }

    public function testAMaximumBelowTheMinimumIsRejected(): void
    {
        $this->expectException(InvalidCoverage::class);
        ($this->set())(1, 10, $this->input(min: '5', max: '4'));
    }

    public function testAZeroRateAsksForConfirmation(): void
    {
        try {
            ($this->set())(1, 10, $this->input(perKg: '0'));
            $this->fail();
        } catch (CoverageConfirmationRequired $e) {
            $this->assertSame('zero_rate', $e->what());
        }
        $this->assertSame([], $this->coverage->ofWarehouse(1));

        $row = ($this->set())(1, 10, $this->input(perKg: '0', zero: true));
        $this->assertTrue($row->coverage->rates()->perKg->isZero());
    }

    public function testAnEmptyMaximumAsksForConfirmation(): void
    {
        try {
            ($this->set())(1, 10, $this->input(max: null));
            $this->fail();
        } catch (CoverageConfirmationRequired $e) {
            $this->assertSame('no_max', $e->what());
        }
        $row = ($this->set())(1, 10, $this->input(max: null, noMax: true));
        $this->assertTrue($row->coverage->rates()->hasNoMax());
    }

    public function testStoppingDeactivatesTheRowWithoutDeletingIt(): void
    {
        ($this->set())(1, 10, $this->input());
        $stop = new SetCoverageActive($this->coverage, $this->municipalities, $this->currencies);

        $this->assertFalse($stop(1, 10, false)->coverage->isActive());
        $this->assertCount(1, $this->coverage->ofWarehouse(1));
        $this->assertTrue($stop(1, 10, true)->coverage->isActive());
    }

    public function testStoppingAnUnknownCoverageFails(): void
    {
        $this->expectException(CoverageNotFound::class);
        (new SetCoverageActive($this->coverage, $this->municipalities, $this->currencies))(1, 10, false);
    }

    public function testTheCurrencyIsPerWarehouseAndDefaultsToEur(): void
    {
        $set = new SetDeliveryCurrency($this->warehouses, $this->currencies);

        $this->assertSame('USD', $set(1, 'usd'));
        ($this->set())(1, 10, $this->input());
        $this->assertSame('USD', (new ListCoverage($this->coverage, $this->currencies, $this->warehouses, $this->municipalities))(1)->currency);
        $this->assertSame('EUR', (new ListCoverage($this->coverage, $this->currencies, $this->warehouses, $this->municipalities))(2)->currency);
    }

    public function testAnInvalidCurrencyIsRejected(): void
    {
        $this->expectException(InvalidCoverage::class);
        (new SetDeliveryCurrency($this->warehouses, $this->currencies))(1, 'EURO');
    }

    private function bulk(): BulkCoverageByProvince
    {
        return new BulkCoverageByProvince($this->coverage, $this->warehouses, $this->municipalities);
    }

    public function testTheBulkPreviewCountsWithoutChangingAnything(): void
    {
        ($this->set())(1, 10, $this->input());

        $preview = ($this->bulk())(1, 1, $this->input(perKg: '0.50'), false, false);

        $this->assertSame(1, $preview->create, 'Playa se crearía (Cerro está inactivo)');
        $this->assertSame(0, $preview->update);
        $this->assertSame(1, $preview->skipped);
        $this->assertFalse($preview->applied);
        $this->assertCount(1, $this->coverage->ofWarehouse(1));
    }

    public function testTheBulkAppliesAndDoesNotOverwriteWithoutTheOption(): void
    {
        ($this->set())(1, 10, $this->input());

        $result = ($this->bulk())(1, 1, $this->input(perKg: '0.50'), false, true);

        $this->assertSame([1, 0, 1, true], [$result->create, $result->update, $result->skipped, $result->applied]);
        $this->assertSame('0.3500', $this->coverage->find(1, 10)?->rates()->perKg->value());
        $this->assertSame('0.5000', $this->coverage->find(1, 11)?->rates()->perKg->value());
    }

    public function testTheBulkWithOverwriteUpdatesTheExistingOnes(): void
    {
        ($this->set())(1, 10, $this->input());

        $result = ($this->bulk())(1, 1, $this->input(perKg: '0.50'), true, true);

        $this->assertSame([1, 1, 0], [$result->create, $result->update, $result->skipped]);
        $this->assertSame('0.5000', $this->coverage->find(1, 10)?->rates()->perKg->value());
    }

    public function testRepeatingTheBulkWithoutOverwriteChangesNothing(): void
    {
        ($this->bulk())(1, 1, $this->input(), false, true);
        $before = serialize(array_map(static fn ($c) => $c->rates()->perKg->value(), $this->coverage->items));

        $second = ($this->bulk())(1, 1, $this->input(perKg: '9'), false, true);

        $this->assertSame([0, 0, 2], [$second->create, $second->update, $second->skipped]);
        $this->assertSame($before, serialize(array_map(static fn ($c) => $c->rates()->perKg->value(), $this->coverage->items)));
    }

    public function testTheBulkNeedsAnActiveWarehouseAndTheSameConfirmations(): void
    {
        try {
            ($this->bulk())(3, 1, $this->input(), false, true);
            $this->fail();
        } catch (CoverageWarehouseInvalid) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(CoverageConfirmationRequired::class);
        ($this->bulk())(1, 1, $this->input(perL: '0'), false, true);
    }

    private function serving(): FindServingWarehouse
    {
        return new FindServingWarehouse($this->coverage, $this->warehouses);
    }

    public function testTheLowestPriorityServes(): void
    {
        ($this->set())(1, 10, $this->input(priority: 2));
        ($this->set())(2, 10, $this->input(priority: 1));

        $serving = ($this->serving())(10);

        $this->assertSame(2, $serving?->coverage->warehouseId());
        $this->assertFalse($serving?->tie);
    }

    public function testATieIsBrokenByTheLowestWarehouseIdAndFlagged(): void
    {
        ($this->set())(2, 10, $this->input(priority: 1));
        ($this->set())(1, 10, $this->input(priority: 1));

        $serving = ($this->serving())(10);

        $this->assertSame(1, $serving?->coverage->warehouseId());
        $this->assertTrue($serving?->tie);
    }

    public function testADraftOrInactiveWarehouseServesNothing(): void
    {
        ($this->set())(1, 10, $this->input());
        $this->warehouses->add(1, 'Principal', 'inactive');

        $this->assertNull(($this->serving())(10));
    }

    public function testAStoppedCoverageDoesNotServe(): void
    {
        ($this->set())(1, 10, $this->input());
        (new SetCoverageActive($this->coverage, $this->municipalities, $this->currencies))(1, 10, false);

        $this->assertNull(($this->serving())(10));
    }

    public function testTheListFlagsTiesAndAMoreExpensiveBackup(): void
    {
        ($this->set())(1, 10, $this->input(perKg: '0.35', priority: 1));
        ($this->set())(2, 10, $this->input(perKg: '0.60', priority: 2));
        $list = new ListCoverage($this->coverage, $this->currencies, $this->warehouses, $this->municipalities);

        $main = $list(1)->rows[0];
        $backup = $list(2)->rows[0];

        $this->assertTrue($main->isServing);
        $this->assertFalse($main->backupMoreExpensive);
        $this->assertFalse($backup->isServing);
        $this->assertTrue($backup->backupMoreExpensive);
    }

    public function testTheListFlagsATieOnBothRows(): void
    {
        ($this->set())(1, 10, $this->input(priority: 1));
        ($this->set())(2, 10, $this->input(priority: 1));
        $list = new ListCoverage($this->coverage, $this->currencies, $this->warehouses, $this->municipalities);

        $this->assertTrue($list(1)->rows[0]->tie);
        $this->assertTrue($list(2)->rows[0]->tie);
    }

    public function testTheListOfAnUnknownWarehouseFails(): void
    {
        $this->expectException(CoverageWarehouseInvalid::class);
        (new ListCoverage($this->coverage, $this->currencies, $this->warehouses, $this->municipalities))(99);
    }
}
