<?php

namespace App\Tests\Service;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use App\Service\SaleSubtype;
use PHPUnit\Framework\TestCase;

/**
 * COMR-62 (C3, ADR-001): todo sitio que bifurca por subtipo de venta decide con este helper o con una rama
 * explícita que lanza ante un subtipo desconocido, en vez de tratarlo en silencio como «paquete» o ignorarlo.
 * Así, cuando llegue el subtipo de mercado, cualquier sitio olvidado falla en las pruebas.
 *
 * @covers \App\Service\SaleSubtype
 */
class SaleSubtypeTest extends TestCase
{
    public function testARechargeIsTheRechargeSaleType(): void
    {
        $this->assertSame('recharge', SaleSubtype::saleTypeOf(new CommunicationSaleRecharge()));
    }

    public function testAPackageIsTheSaleSaleType(): void
    {
        $this->assertSame('sale', SaleSubtype::saleTypeOf(new CommunicationSalePackage()));
    }

    public function testAnyOtherSubtypeThrowsADomainErrorNamingTheClass(): void
    {
        try {
            SaleSubtype::saleTypeOf(new CommunicationSaleInfo());
            $this->fail('Un subtipo desconocido debe lanzar.');
        } catch (MyCurrentException $e) {
            $this->assertSame('UNSUPPORTED_SALE_SUBTYPE', $e->getCodeWork());
            $this->assertSame(500, $e->getCode());
            $this->assertStringContainsString(CommunicationSaleInfo::class, $e->getMessage());
        }
    }

    public function testUnsupportedBuildsTheSameErrorForSitesThatBranchOnTheirOwn(): void
    {
        $e = SaleSubtype::unsupported(new CommunicationSaleInfo());

        $this->assertSame('UNSUPPORTED_SALE_SUBTYPE', $e->getCodeWork());
        $this->assertSame(500, $e->getCode());
    }
}
