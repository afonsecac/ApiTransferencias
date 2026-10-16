<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Domain;

use App\Market\Distribucion\Domain\DeliveryAmount;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryRates;
use App\Market\Distribucion\Domain\InvalidCoverage;
use PHPUnit\Framework\TestCase;

final class DeliveryCoverageTest extends TestCase
{
    private function rates(string $perKg = '0.3500', string $perL = '0.4000', string $min = '2.0000', ?string $max = '12.0000'): DeliveryRates
    {
        return new DeliveryRates(DeliveryAmount::fromString($perKg), DeliveryAmount::fromString($perL), DeliveryAmount::fromString($min), $max === null ? null : DeliveryAmount::fromString($max));
    }

    public function testAnAmountKeepsFourDecimalsExactly(): void
    {
        $this->assertSame('0.3500', DeliveryAmount::fromString('0.35')->value());
        $this->assertSame('12.0000', DeliveryAmount::fromString('12')->value());
        $this->assertSame('0.0001', DeliveryAmount::fromString('0.0001')->value());
        $this->assertSame(3500, DeliveryAmount::fromString('0.35')->units());
    }

    public function testAnAmountRejectsNegativeOrOverPreciseOrNonNumericValues(): void
    {
        foreach (['-1', '0.12345', 'abc', '', '1,5'] as $bad) {
            try {
                DeliveryAmount::fromString($bad);
                $this->fail('debía rechazar ' . $bad);
            } catch (InvalidCoverage) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAmountsCompare(): void
    {
        $this->assertTrue(DeliveryAmount::fromString('2')->isGreaterThan(DeliveryAmount::fromString('1.9999')));
        $this->assertTrue(DeliveryAmount::fromString('0')->isZero());
    }

    public function testTheMaximumCannotBeBelowTheMinimum(): void
    {
        $this->expectException(InvalidCoverage::class);
        $this->rates(min: '5', max: '4.9999');
    }

    public function testAnEmptyMaximumMeansNoCap(): void
    {
        $rates = $this->rates(max: null);

        $this->assertTrue($rates->hasNoMax());
        $this->assertFalse($this->rates()->hasNoMax());
    }

    public function testItDetectsAZeroRate(): void
    {
        $this->assertTrue($this->rates(perKg: '0')->hasZeroRate());
        $this->assertTrue($this->rates(perL: '0')->hasZeroRate());
        $this->assertFalse($this->rates()->hasZeroRate());
    }

    public function testACoverageStartsActiveWithLinearRates(): void
    {
        $coverage = DeliveryCoverage::create(3, 11, $this->rates(), 1);

        $this->assertTrue($coverage->isActive());
        $this->assertSame(3, $coverage->warehouseId());
        $this->assertSame(11, $coverage->municipalityId());
        $this->assertSame(1, $coverage->priority());
        $this->assertSame('linear', $coverage->rateMode());
        $this->assertSame('0.3500', $coverage->rates()->perKg->value());
    }

    public function testThePriorityIsAPositiveNumber(): void
    {
        $this->expectException(InvalidCoverage::class);
        DeliveryCoverage::create(3, 11, $this->rates(), 0);
    }

    public function testItIsRepricedAndStoppedWithoutBeingDeleted(): void
    {
        $coverage = DeliveryCoverage::create(3, 11, $this->rates(), 1);
        $coverage->reprice($this->rates(perKg: '0.5'), 2);
        $coverage->stop();

        $this->assertSame('0.5000', $coverage->rates()->perKg->value());
        $this->assertSame(2, $coverage->priority());
        $this->assertFalse($coverage->isActive());

        $coverage->resume();
        $this->assertTrue($coverage->isActive());
    }
}
