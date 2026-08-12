<?php

namespace App\Tests\Service\Pricing;

use App\Service\Pricing\DestinationKey;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\DestinationKey
 */
class DestinationKeyTest extends TestCase
{
    public function testMatchesIsTrueForIdenticalTuples(): void
    {
        $this->assertTrue(DestinationKey::matches(500.0, 'CUP', 500.0, 'CUP'));
    }

    public function testMatchesIsCaseInsensitiveOnCurrency(): void
    {
        $this->assertTrue(DestinationKey::matches(500.0, 'cup', 500.0, 'CUP'));
    }

    public function testMatchesToleratesFloatingPointNoiseWithinTwoDecimals(): void
    {
        // Mismo escenario real que motivó el helper: (250/100)*22 no da
        // exactamente 55.0 en coma flotante.
        $computed = (250 / 100) * 22;

        $this->assertTrue(DestinationKey::matches($computed, 'CUP', 55.0, 'CUP'));
    }

    public function testMatchesIsFalseForDifferentAmount(): void
    {
        $this->assertFalse(DestinationKey::matches(500.0, 'CUP', 500.5, 'CUP'));
    }

    public function testMatchesIsFalseForDifferentCurrency(): void
    {
        $this->assertFalse(DestinationKey::matches(500.0, 'CUP', 500.0, 'USD'));
    }

    public function testOfTrimsAndUppercasesCurrency(): void
    {
        $this->assertSame(DestinationKey::of(10.0, 'usd'), DestinationKey::of(10.0, ' USD '));
    }

    public function testOfRoundsToTwoDecimals(): void
    {
        $this->assertSame(DestinationKey::of(10.001, 'USD'), DestinationKey::of(10.0, 'USD'));
    }
}
