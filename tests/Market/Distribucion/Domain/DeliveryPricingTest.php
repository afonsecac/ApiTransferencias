<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Domain;

use App\Market\Distribucion\Domain\BillableLoad;
use App\Market\Distribucion\Domain\DeliveryAmount;
use App\Market\Distribucion\Domain\DeliveryPricing;
use App\Market\Distribucion\Domain\DeliveryRates;
use PHPUnit\Framework\TestCase;

/**
 * La fórmula de COMR-73 con la cobertura de La Habana: 0,60 EUR/kg, 0,45 EUR/L, mínimo 2,50, máximo 12,00.
 */
final class DeliveryPricingTest extends TestCase
{
    private function rates(string $perKg = '0.60', string $perL = '0.45', string $min = '2.50', ?string $max = '12.00'): DeliveryRates
    {
        return new DeliveryRates(DeliveryAmount::fromString($perKg), DeliveryAmount::fromString($perL), DeliveryAmount::fromString($min), $max === null ? null : DeliveryAmount::fromString($max));
    }

    /** @param list<BillableLoad> $loads */
    private function price(array $loads, ?DeliveryRates $rates = null): \App\Market\Distribucion\Domain\PriceBreakdown
    {
        return DeliveryPricing::price($rates ?? $this->rates(), $loads);
    }

    /** @return iterable<string, array{list<array{string, string}>, string, string}> */
    public static function acceptanceCases(): iterable
    {
        yield '0,8 kg sube al mínimo' => [[['kg', '0.800000']], '2.50', 'min'];
        yield '5 kg' => [[['kg', '5.000000']], '3.00', 'none'];
        yield '30 kg baja al máximo' => [[['kg', '30.000000']], '12.00', 'max'];
        yield '20 kg justo en el máximo' => [[['kg', '20.000000']], '12.00', 'none'];
        yield '120 kg' => [[['kg', '120.000000']], '12.00', 'max'];
        yield '40 L' => [[['l', '40.000000']], '12.00', 'max'];
        yield '12 kg + 10 L' => [[['kg', '12.000000'], ['l', '10.000000']], '11.70', 'none'];
        yield '4,166667 kg redondea al mínimo' => [[['kg', '4.166667']], '2.50', 'none'];
        yield '26,666667 L redondea al máximo' => [[['l', '26.666667']], '12.00', 'none'];
        yield 'sin carga cobra el mínimo' => [[], '2.50', 'min'];
    }

    /**
     * @param list<array{string, string}> $specs
     *
     * @dataProvider acceptanceCases
     */
    public function testAcceptanceCases(array $specs, string $total, string $limit): void
    {
        $price = $this->price(array_map(static fn (array $s) => BillableLoad::of($s[0] === 'kg' ? BillableLoad::WEIGHT : BillableLoad::VOLUME, $s[1]), $specs));

        $this->assertSame($total, $price->total());
        $this->assertSame($limit, $price->limit);
    }

    public function testWithoutMaximumNothingCapsThePrice(): void
    {
        $price = $this->price([BillableLoad::of(BillableLoad::WEIGHT, '120.000000')], $this->rates(max: null));

        $this->assertSame('72.00', $price->total());
        $this->assertSame('none', $price->limit);
    }

    public function testEachLineIsRoundedHalfUpBeforeSummingAndBeforeTheLimits(): void
    {
        $rates = $this->rates(perKg: '0.01', perL: '0.01', min: '0', max: null);
        // 0,5 kg × 0,01 = 0,005 → 0,01 cada línea; dos líneas → 0,02 (y no 0,01 sumando antes de redondear).
        $price = $this->price([BillableLoad::of(BillableLoad::WEIGHT, '0.500000'), BillableLoad::of(BillableLoad::WEIGHT, '0.500000')], $rates);

        $this->assertSame('0.02', $price->subtotal());
        $this->assertSame(['0.01', '0.01'], array_map(static fn ($l) => $l->amount(), $price->lines));
    }

    public function testTheBreakdownSeparatesTheKgPartFromTheLiterPart(): void
    {
        $price = $this->price([BillableLoad::of(BillableLoad::WEIGHT, '12.000000'), BillableLoad::of(BillableLoad::VOLUME, '10.000000')]);

        $this->assertSame('7.20', $price->kgAmount());
        $this->assertSame('4.50', $price->litersAmount());
        $this->assertSame('11.70', $price->subtotal());
    }

    public function testTheMinimumAndMaximumAreAppliedOnceOverTheSumNotPerLine(): void
    {
        // Dos líneas de 0,8 kg: 0,48 + 0,48 = 0,96 → un solo mínimo (2,50), no dos.
        $price = $this->price([BillableLoad::of(BillableLoad::WEIGHT, '0.800000'), BillableLoad::of(BillableLoad::WEIGHT, '0.800000')]);

        $this->assertSame('2.50', $price->total());
        $this->assertSame('min', $price->limit);
    }

    public function testAZeroRateChargesNothingForThatPart(): void
    {
        $price = $this->price([BillableLoad::of(BillableLoad::WEIGHT, '10.000000')], $this->rates(perKg: '0', min: '0'));

        $this->assertSame('0.00', $price->total());
    }

    public function testTheLoadKeepsItsRealAndVolumetricWeight(): void
    {
        $load = BillableLoad::of(BillableLoad::WEIGHT, '8.000000', '2.400000', '8.000000');

        $this->assertSame('2.400000', $load->realValue());
        $this->assertSame('8.000000', $load->volumetricValue());
        $this->assertSame('8.000000', $load->value());
    }

    public function testThePriceNeverDecreasesWithLoadAndStaysBetweenMinimumAndMaximum(): void
    {
        mt_srand(73);
        $rates = $this->rates();
        $previous = 0;
        for ($kgMicro = 0; $kgMicro <= 40_000_000; $kgMicro += mt_rand(1, 400_000)) {
            $price = $this->price([BillableLoad::fromMicro(BillableLoad::WEIGHT, $kgMicro)], $rates);
            $cents = (int) str_replace('.', '', $price->total());
            $this->assertGreaterThanOrEqual($previous, $cents, 'el precio no decrece con la carga');
            $this->assertGreaterThanOrEqual(250, $cents);
            $this->assertLessThanOrEqual(1200, $cents);
            $previous = $cents;
        }
    }

    public function testItRejectsAnInvalidLoad(): void
    {
        $this->expectException(\App\Market\Distribucion\Domain\InvalidQuote::class);
        BillableLoad::of(BillableLoad::WEIGHT, '-1');
    }

    public function testItRejectsMoreThanSixDecimals(): void
    {
        $this->expectException(\App\Market\Distribucion\Domain\InvalidQuote::class);
        BillableLoad::of(BillableLoad::WEIGHT, '1.1234567');
    }
}
