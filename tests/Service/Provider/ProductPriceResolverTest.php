<?php

namespace App\Tests\Service\Provider;

use App\Entity\CommunicationProduct;
use App\Service\Provider\CurrencyConversionService;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ResolvedExchangeRate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ProductPriceResolver
 */
class ProductPriceResolverTest extends TestCase
{
    private CurrencyConversionService&MockObject $currencyConversionService;
    private ProductPriceResolver $resolver;

    protected function setUp(): void
    {
        $this->currencyConversionService = $this->createMock(CurrencyConversionService::class);
        $this->resolver = new ProductPriceResolver($this->currencyConversionService, new NullLogger());
    }

    private function product(float $price, ?string $priceCurrency): CommunicationProduct&MockObject
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getId')->willReturn(1);
        $product->method('getPrice')->willReturn($price);
        $product->method('getPriceCurrency')->willReturn($priceCurrency);

        return $product;
    }

    public function testReturnsWholesalePriceUnchangedWhenCurrenciesMatch(): void
    {
        $this->currencyConversionService->expects($this->never())->method('getRateDetails');

        $result = $this->resolver->resolve($this->product(4.5, 'USD'), 'USD');

        $this->assertSame(4.5, $result->amount);
        $this->assertSame('USD', $result->currency);
        $this->assertNull($result->conversionRate);
        $this->assertNull($result->conversionRateDate);
        $this->assertNull($result->pendingNote);
    }

    public function testReturnsWholesalePriceWhenClientCurrencyUnknown(): void
    {
        $result = $this->resolver->resolve($this->product(4.5, 'USD'), null);

        $this->assertSame(4.5, $result->amount);
        $this->assertSame('USD', $result->currency);
    }

    public function testConvertsAndRecordsRateAndDateWhenCurrenciesDiffer(): void
    {
        $rateDate = new \DateTimeImmutable('2026-07-31');
        $this->currencyConversionService->method('getRateDetails')
            ->with('USD', 'EUR')
            ->willReturn(new ResolvedExchangeRate(0.89, $rateDate));

        $result = $this->resolver->resolve($this->product(4.5, 'USD'), 'EUR');

        $this->assertSame(4.01, $result->amount);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame(0.89, $result->conversionRate);
        $this->assertSame($rateDate, $result->conversionRateDate);
        $this->assertNull($result->pendingNote);
    }

    public function testFallsBackToWholesalePriceWithPendingNoteWhenRateMissing(): void
    {
        $this->currencyConversionService->method('getRateDetails')->willReturn(null);

        $result = $this->resolver->resolve($this->product(4.5, 'USD'), 'EUR', 101);

        $this->assertSame(4.5, $result->amount);
        $this->assertSame('USD', $result->currency);
        $this->assertNull($result->conversionRate);
        $this->assertNull($result->conversionRateDate);
        $this->assertNotNull($result->pendingNote);
        $this->assertStringContainsString('Pendiente conversión de moneda', $result->pendingNote);
    }
}
