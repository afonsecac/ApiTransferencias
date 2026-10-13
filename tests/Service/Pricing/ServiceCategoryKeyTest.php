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

    // ---- COMR-264 (C1): area opcional en la clave ----

    public function testOfWithoutAreaIsExactlyTheKeyOfToday(): void
    {
        $this->assertSame('Mobile|AIRTIME', ServiceCategoryKey::of('Mobile', 'AIRTIME', null));
        $this->assertSame('Mobile|AIRTIME', ServiceCategoryKey::of('Mobile', 'AIRTIME', ''));
        $this->assertSame('Mobile|AIRTIME', ServiceCategoryKey::of('Mobile', 'AIRTIME', '   '));
        $this->assertSame('|', ServiceCategoryKey::of(null, null, null));
    }

    public function testOfWithAreaAppendsItAsAThirdSegment(): void
    {
        $this->assertSame('Mobile|AIRTIME|Retail', ServiceCategoryKey::of('Mobile', 'AIRTIME', 'Retail'));
        $this->assertSame('Mobile||Retail', ServiceCategoryKey::of('Mobile', null, 'Retail'));
    }

    public function testOfTrimsTheArea(): void
    {
        $this->assertSame('Mobile|AIRTIME|Retail', ServiceCategoryKey::of('Mobile', 'AIRTIME', ' Retail '));
    }

    public function testFromServiceIncludesTheAreaWhenPresent(): void
    {
        $service = ['name' => 'Gift', 'subservice' => ['name' => 'Card'], 'area' => ['name' => 'Retail']];

        $this->assertSame('Gift|Card|Retail', ServiceCategoryKey::fromService($service));
    }

    public function testFromServiceWithoutAreaKeepsTheKeyOfToday(): void
    {
        $this->assertSame('Gift|Card', ServiceCategoryKey::fromService(['name' => 'Gift', 'subservice' => ['name' => 'Card']]));
    }

    /**
     * @dataProvider categoryMembership
     */
    public function testIsInCategoryAcceptsTheSameKeyOrOneWithAnAreaSuffix(string $key, string $category, bool $expected): void
    {
        $this->assertSame($expected, ServiceCategoryKey::isInCategory($key, $category));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function categoryMembership(): iterable
    {
        yield 'la misma clave' => ['Mobile|AIRTIME', 'Mobile|AIRTIME', true];
        yield 'con area' => ['Mobile|AIRTIME|Retail', 'Mobile|AIRTIME', true];
        yield 'sin subservicio, con area' => ['Mobile||Retail', 'Mobile|', true];
        yield 'subservicio distinto' => ['Mobile|DATA', 'Mobile|AIRTIME', false];
        yield 'prefijo de otro nombre' => ['Mobile|AIRTIMEX', 'Mobile|AIRTIME', false];
        yield 'otro servicio' => ['Utilities|AIRTIME', 'Mobile|AIRTIME', false];
        yield 'la categoria es mas especifica' => ['Mobile|AIRTIME', 'Mobile|AIRTIME|Retail', false];
    }
}
