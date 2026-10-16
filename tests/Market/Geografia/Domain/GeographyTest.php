<?php

declare(strict_types=1);

namespace App\Tests\Market\Geografia\Domain;

use App\Market\Geografia\Domain\InvalidGeography;
use App\Market\Geografia\Domain\Municipality;
use App\Market\Geografia\Domain\Province;
use PHPUnit\Framework\TestCase;

final class GeographyTest extends TestCase
{
    public function testAProvinceStartsActiveAndKeepsItsNormalizedName(): void
    {
        $province = Province::create('la-habana', 'La Habana');

        $this->assertTrue($province->isActive());
        $this->assertFalse($province->isSpecial());
        $this->assertSame('la habana', $province->searchName());
    }

    public function testASpecialProvinceIsMarked(): void
    {
        $this->assertTrue(Province::create('isla-de-la-juventud', 'Isla de la Juventud', true)->isSpecial());
    }

    public function testAProvinceCanBeDeactivatedAndActivated(): void
    {
        $province = Province::create('matanzas', 'Matanzas');
        $province->deactivate();
        $this->assertFalse($province->isActive());
        $province->activate();
        $this->assertTrue($province->isActive());
    }

    public function testAMunicipalityKeepsItsCodeAndNormalizedName(): void
    {
        $municipality = Municipality::create(3, 'guines', 'Güines');

        $this->assertSame(3, $municipality->provinceId());
        $this->assertSame('guines', $municipality->code());
        $this->assertSame('guines', $municipality->searchName());
        $this->assertNull($municipality->oneiCode());
        $this->assertTrue($municipality->isActive());
    }

    public function testAMunicipalityCanBeDeactivated(): void
    {
        $municipality = Municipality::create(1, 'boyeros', 'Boyeros');
        $municipality->deactivate();

        $this->assertFalse($municipality->isActive());
    }

    public function testItRejectsBlankNamesAndBadCodes(): void
    {
        $this->expectException(InvalidGeography::class);
        Province::create('matanzas', '  ');
    }

    public function testItRejectsACodeWithUppercase(): void
    {
        $this->expectException(InvalidGeography::class);
        Municipality::create(1, 'Boyeros', 'Boyeros');
    }

    public function testAMunicipalityNeedsAProvince(): void
    {
        $this->expectException(InvalidGeography::class);
        Municipality::create(0, 'boyeros', 'Boyeros');
    }
}
