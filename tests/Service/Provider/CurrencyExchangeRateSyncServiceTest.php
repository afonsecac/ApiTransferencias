<?php

namespace App\Tests\Service\Provider;

use App\Entity\CurrencyExchangeRate;
use App\Repository\CurrencyExchangeRateRepository;
use App\Service\Provider\CurrencyExchangeRateSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \App\Service\Provider\CurrencyExchangeRateSyncService
 */
class CurrencyExchangeRateSyncServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CurrencyExchangeRateRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(CurrencyExchangeRateRepository::class);
        $this->em->method('getRepository')->willReturn($this->repo);
    }

    public function testFetchesFromEurBaseAndPersistsOneRowPerCurrency(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('from=EUR', $url);
            $this->assertStringNotContainsString('to=', $url);

            return new MockResponse(json_encode([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-07-31',
                'rates' => ['USD' => 1.1, 'GBP' => 0.88],
            ]));
        });

        $this->repo->method('findOneBy')->willReturn(null);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $service = new CurrencyExchangeRateSyncService($httpClient, $this->em, new NullLogger());
        $result = $service->sync();

        $this->assertSame(2, $result->created);
        $this->assertSame('2026-07-31', $result->rateDate);
        $this->assertSame('EUR', $result->baseCurrency);
        $this->assertCount(2, $persisted);

        $byTarget = [];
        foreach ($persisted as $row) {
            $this->assertInstanceOf(CurrencyExchangeRate::class, $row);
            $this->assertSame('EUR', $row->getBaseCurrency());
            $this->assertSame('2026-07-31', $row->getRateDate()->format('Y-m-d'));
            $byTarget[$row->getTargetCurrency()] = $row->getRate();
        }
        $this->assertSame(['USD' => 1.1, 'GBP' => 0.88], $byTarget);
    }

    public function testSkipsCurrenciesAlreadyStoredForThatRateDate(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse(json_encode([
                'date' => '2026-07-31',
                'rates' => ['USD' => 1.1],
            ]));
        });

        // Ya existe una fila para EUR->USD en esa rate_date: no debe duplicarse.
        $this->repo->method('findOneBy')->willReturn($this->createMock(CurrencyExchangeRate::class));

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $service = new CurrencyExchangeRateSyncService($httpClient, $this->em, new NullLogger());

        $this->assertSame(0, $service->sync()->created);
    }

    public function testThrowsWhenFrankfurterResponseIsMalformed(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse(json_encode(['status' => 'ok']));
        });

        $service = new CurrencyExchangeRateSyncService($httpClient, $this->em, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Respuesta inesperada de Frankfurter');

        $service->sync();
    }

    public function testPropagatesTransportFailures(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('connection refused');
        });

        $service = new CurrencyExchangeRateSyncService($httpClient, $this->em, new NullLogger());

        $this->expectException(\Throwable::class);

        $service->sync();
    }
}
