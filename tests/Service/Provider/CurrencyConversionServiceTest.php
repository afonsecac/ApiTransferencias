<?php

namespace App\Tests\Service\Provider;

use App\Entity\CurrencyExchangeRate;
use App\Repository\CurrencyExchangeRateRepository;
use App\Service\Provider\CurrencyConversionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\CurrencyConversionService
 *
 * Lee siempre del histórico ya guardado (currency_exchange_rate) — nunca
 * llama a Frankfurter en vivo. Ver CurrencyExchangeRateSyncServiceTest para
 * la sincronización en sí.
 */
class CurrencyConversionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CurrencyExchangeRateRepository&MockObject $repo;
    private CurrencyConversionService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(CurrencyExchangeRateRepository::class);
        $this->em->method('getRepository')->willReturn($this->repo);

        $this->service = new CurrencyConversionService($this->em, new NullLogger());
    }

    private function rateRow(string $base, string $target, float $rate): CurrencyExchangeRate
    {
        return (new CurrencyExchangeRate())
            ->setBaseCurrency($base)
            ->setTargetCurrency($target)
            ->setRate($rate)
            ->setRateDate(new \DateTimeImmutable('2026-07-31'))
            ->setFetchedAt(new \DateTimeImmutable('now'));
    }

    public function testReturnsSameAmountWhenCurrenciesMatch(): void
    {
        $this->repo->expects($this->never())->method('findLatest');

        $this->assertSame(100.0, $this->service->convert(100.0, 'USD', 'usd'));
    }

    public function testConvertsFromBaseCurrency(): void
    {
        // EUR es la base guardada: EUR->USD es una lectura directa.
        $this->repo->method('findLatest')
            ->with('EUR', 'USD')
            ->willReturn($this->rateRow('EUR', 'USD', 1.1));

        $this->assertSame(110.0, $this->service->convert(100.0, 'EUR', 'USD'));
    }

    public function testConvertsToBaseCurrencyByInvertingRate(): void
    {
        // USD->EUR: se invierte la tasa guardada EUR->USD.
        $this->repo->method('findLatest')
            ->with('EUR', 'USD')
            ->willReturn($this->rateRow('EUR', 'USD', 2.0));

        $this->assertSame(50.0, $this->service->convert(100.0, 'USD', 'EUR'));
    }

    public function testConvertsCrossPairByCombiningTwoBaseRates(): void
    {
        // USD->GBP no es EUR en ninguno de los dos lados: se deriva
        // combinando EUR->USD y EUR->GBP.
        $this->repo->method('findLatest')->willReturnMap([
            ['EUR', 'USD', $this->rateRow('EUR', 'USD', 1.1)],
            ['EUR', 'GBP', $this->rateRow('EUR', 'GBP', 0.88)],
        ]);

        // 100 USD -> EUR: 100/1.1 = 90.909... -> GBP: *0.88 = 80.00
        $this->assertSame(80.0, $this->service->convert(100.0, 'USD', 'GBP'));
    }

    public function testReturnsNullWhenNoRateStoredForPair(): void
    {
        $this->repo->method('findLatest')->willReturn(null);

        $this->assertNull($this->service->convert(100.0, 'USD', 'EUR'));
    }

    public function testReturnsNullForCrossPairWhenOnlyOneSideIsStored(): void
    {
        $this->repo->method('findLatest')->willReturnMap([
            ['EUR', 'USD', $this->rateRow('EUR', 'USD', 1.1)],
            ['EUR', 'GBP', null],
        ]);

        $this->assertNull($this->service->convert(100.0, 'USD', 'GBP'));
    }

    public function testCachesRateWithinTheSameServiceInstance(): void
    {
        $this->repo->expects($this->once())
            ->method('findLatest')
            ->with('EUR', 'USD')
            ->willReturn($this->rateRow('EUR', 'USD', 1.1));

        $this->assertSame(110.0, $this->service->convert(100.0, 'EUR', 'USD'));
        // Segunda conversión con el mismo par: no debe volver a consultar la BD.
        $this->assertSame(55.0, $this->service->convert(50.0, 'EUR', 'USD'));
    }

    public function testGetRateReturnsOneForSameCurrency(): void
    {
        $this->repo->expects($this->never())->method('findLatest');

        $this->assertSame(1.0, $this->service->getRate('EUR', 'EUR'));
    }

    public function testGetRateDetailsExposesRateDateFromBaseCurrency(): void
    {
        $this->repo->method('findLatest')
            ->with('EUR', 'USD')
            ->willReturn($this->rateRow('EUR', 'USD', 1.1));

        $details = $this->service->getRateDetails('EUR', 'USD');

        $this->assertSame(1.1, $details->rate);
        $this->assertSame('2026-07-31', $details->rateDate->format('Y-m-d'));
    }

    public function testGetRateDetailsReturnsNullDateForSameCurrency(): void
    {
        $details = $this->service->getRateDetails('EUR', 'eur');

        $this->assertSame(1.0, $details->rate);
        $this->assertNull($details->rateDate);
    }
}
