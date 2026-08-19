<?php

namespace App\Tests\Service\Provider;

use App\Entity\CommunicationProduct;
use App\Service\Provider\ProductSaleTypeMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\ProductSaleTypeMatcher
 *
 * Extraído de ClientCatalogImportService::matchesSaleType() (V2 Fase 1) —
 * mismo criterio, ahora compartido también por ProviderDispatchResolver
 * (Fase 2). ClientCatalogImportServiceTest ya cubre este criterio de forma
 * indirecta (a través del import); este test cubre la unidad en sí.
 */
class ProductSaleTypeMatcherTest extends TestCase
{
    private ProductSaleTypeMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ProductSaleTypeMatcher();
    }

    private function product(string $packageType, bool $isMobileOrInternetService): CommunicationProduct
    {
        return (new CommunicationProduct())
            ->setPackageType($packageType)
            ->setIsMobileOrInternetService($isMobileOrInternetService);
    }

    public function testNullSaleTypeAlwaysMatches(): void
    {
        $this->assertTrue($this->matcher->matches($this->product('PIN_PURCHASE', false), null));
        $this->assertTrue($this->matcher->matches($this->product('RECHARGE', true), null));
    }

    public function testRechargeSaleTypeMatchesMobileRechargeableProduct(): void
    {
        $this->assertTrue($this->matcher->matches($this->product('RECHARGE', true), 'recharge'));
    }

    public function testRechargeSaleTypeExcludesPinPurchase(): void
    {
        $this->assertFalse($this->matcher->matches($this->product('PIN_PURCHASE', true), 'recharge'));
    }

    public function testRechargeSaleTypeExcludesNonMobileOrInternetService(): void
    {
        $this->assertFalse($this->matcher->matches($this->product('RECHARGE', false), 'recharge'));
    }

    public function testSaleSaleTypeIsTheInverseOfRecharge(): void
    {
        $rechargeable = $this->product('RECHARGE', true);
        $pinPurchase = $this->product('PIN_PURCHASE', true);
        $nonMobile = $this->product('RECHARGE', false);

        $this->assertFalse($this->matcher->matches($rechargeable, 'sale'));
        $this->assertTrue($this->matcher->matches($pinPurchase, 'sale'));
        $this->assertTrue($this->matcher->matches($nonMobile, 'sale'));
    }
}
