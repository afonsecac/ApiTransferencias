<?php

namespace App\Tests\Service\Pricing;

use App\Service\Pricing\ServiceCategoryKey;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\ServiceCategoryKey
 */
class ServiceCategoryKeyTest extends TestCase
{
    public function testMatchesIsTrueForIdenticalCategories(): void
    {
        $this->assertTrue(ServiceCategoryKey::matches('Mobile', 'Airtime', 'Mobile', 'Airtime'));
    }

    public function testMatchesIsFalseForDifferentSubservice(): void
    {
        $this->assertFalse(ServiceCategoryKey::matches('Mobile', 'Airtime', 'Mobile', 'Bundle'));
    }

    public function testMatchesIsFalseForDifferentService(): void
    {
        $this->assertFalse(ServiceCategoryKey::matches('Mobile', 'Internet', 'Utilities', 'Internet'));
    }

    public function testMatchesIsCaseSensitive(): void
    {
        // A propósito, sin case-folding — ver docblock de la clase (PHP
        // strtoupper vs Postgres upper divergen con acentos/no-ASCII).
        $this->assertFalse(ServiceCategoryKey::matches('Mobile', 'Airtime', 'mobile', 'airtime'));
    }

    public function testOfTrimsWhitespace(): void
    {
        $this->assertSame(ServiceCategoryKey::of('Mobile', 'Airtime'), ServiceCategoryKey::of(' Mobile ', ' Airtime '));
    }

    public function testOfNeverReturnsNull(): void
    {
        $this->assertSame('|', ServiceCategoryKey::of(null, null));
    }

    public function testOfHandlesMissingSubservice(): void
    {
        $this->assertSame('Mobile|', ServiceCategoryKey::of('Mobile', null));
    }

    public function testFromServiceExtractsNestedShape(): void
    {
        $service = ['name' => 'Utilities', 'subservice' => ['name' => 'Internet']];

        $this->assertSame(ServiceCategoryKey::of('Utilities', 'Internet'), ServiceCategoryKey::fromService($service));
    }

    public function testFromServiceHandlesEmptyArray(): void
    {
        $this->assertSame('|', ServiceCategoryKey::fromService([]));
    }

    public function testFromServiceHandlesServiceWithoutSubserviceKey(): void
    {
        $this->assertSame('Devices|', ServiceCategoryKey::fromService(['name' => 'Devices']));
    }
}
