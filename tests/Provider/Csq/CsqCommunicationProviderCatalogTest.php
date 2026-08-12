<?php

namespace App\Tests\Provider\Csq;

use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use App\Enums\CommunicationProviderEnum;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Provider\Csq\CsqCommunicationProvider::fetchProducts
 */
class CsqCommunicationProviderCatalogTest extends TestCase
{
    private CsqHttpClient&MockObject $client;
    private CsqCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CsqHttpClient::class);
        $this->provider = new CsqCommunicationProvider($this->client, new CsqStatusMapper(), new NullLogger());
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::CSQ, environmentType: 'TEST');
    }

    private function portfolio(array $products): array
    {
        return [['terminalId' => 173103, 'products' => $products]];
    }

    public function testExpandsAByListProductIntoOneCommunicationProductPerAmount(): void
    {
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7951,
                'name' => 'Cubacel  Pack Combos',
                'countryId' => 192,
                'topupType' => 'Bundles',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [2200, 3300, 4400]],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
                'productDescription' => 'Combos',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(3, $products);
        $this->assertSame(['7951-2200', '7951-3300', '7951-4400'], array_map(static fn ($p) => $p->externalId, $products));
        $this->assertSame([100.0, 150.0, 200.0], array_map(static fn ($p) => $p->wholesalePrice, $products));
        $this->assertSame([2200.0, 3300.0, 4400.0], array_map(static fn ($p) => $p->destinationAmount, $products));
        $this->assertSame('CUP', $products[0]->destinationUnit);
        $this->assertSame('USD', $products[0]->priceCurrency);
        $this->assertTrue($products[0]->isMobileOrInternetService);
        $this->assertSame(['name' => 'Mobile', 'subservice' => ['name' => 'Bundle']], $products[0]->service);
    }

    public function testExpandsAByRangeProductIntoMultiplesOfCatalogStep(): void
    {
        // Corrección de diseño (2026-08-10): from/to NO están en moneda de
        // destino — hay que aplicar (valor/100)*exchangeRate. Para Cubacel
        // real (from:1100, to:5500, exchangeRate:22): rango real
        // 242-1210 CUP. Con CATALOG_STEP_CUP=25, el primer múltiplo de 25
        // dentro del rango es 250, el último 1200 -> 39 productos.
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7854,
                'name' => 'Cubacel',
                'countryId' => 192,
                'topupType' => 'RTR',
                'amountType' => 'by_range',
                'saleAmount' => ['from' => 1100, 'to' => 5500, 'step' => 1, 'list' => []],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(39, $products);
        $this->assertSame('7854-250', $products[0]->externalId);
        $this->assertSame(250.0, $products[0]->destinationAmount);
        $this->assertSame(round(250 / 22, 2), $products[0]->wholesalePrice);
        $this->assertSame('7854-1200', $products[array_key_last($products)]->externalId);
        $this->assertTrue($products[0]->isMobileOrInternetService);
        $this->assertSame(['name' => 'Mobile', 'subservice' => ['name' => 'Airtime']], $products[0]->service);
    }

    public function testCatalogStepAndLimitsAreConfigurablePerConstructorParams(): void
    {
        // Configurables vía config/services.yaml (app.csq.catalog_*, ver
        // constructor con #[Autowire(param: ...)]) — probado aquí pasando
        // los valores directo al constructor, sin pasar por el contenedor.
        $client = $this->createMock(CsqHttpClient::class);
        $provider = new CsqCommunicationProvider(
            $client,
            new CsqStatusMapper(),
            new NullLogger(),
            catalogStepCup: 50.0,
            catalogMinAmountCup: 300.0,
            catalogMaxAmountCup: 600.0,
        );

        $client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7854,
                'name' => 'Cubacel',
                'countryId' => 192,
                'topupType' => 'RTR',
                'amountType' => 'by_range',
                // realFrom=242, realTo=1210 sin límites — con min=300/max=600
                // y step=50, el catálogo real queda acotado a 300..600.
                'saleAmount' => ['from' => 1100, 'to' => 5500, 'step' => 1, 'list' => []],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
            ],
        ]));

        $products = iterator_to_array($provider->fetchProducts($this->context()));

        $this->assertSame(
            ['7854-300', '7854-350', '7854-400', '7854-450', '7854-500', '7854-550', '7854-600'],
            array_map(static fn ($p) => $p->externalId, $products),
        );
    }

    public function testByRangeProductWithNoCatalogStepMultipleInRangeIsSkippedWithWarning(): void
    {
        // Rango real más angosto que un solo CATALOG_STEP_CUP (25): ningún
        // múltiplo de 25 cae dentro — se omite en vez de generar 0 productos
        // en silencio o forzar un punto fuera de rango.
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 9001,
                'name' => 'Producto de rango angosto',
                'countryId' => 192,
                'topupType' => 'RTR',
                'amountType' => 'by_range',
                // realFrom=(100/100)*1=1, realTo=(105/100)*1=1.05 — ningún
                // múltiplo de 25 cae ahí.
                'saleAmount' => ['from' => 100, 'to' => 105, 'step' => 1, 'list' => []],
                'exchangeRate' => 1.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'USD',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame([], $products);
    }

    public function testSkipsProductsFromOtherCountries(): void
    {
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7008,
                'name' => 'AT&T Go Phone',
                'countryId' => 840,
                'topupType' => 'RTR',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [1000]],
                'exchangeRate' => 1.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'USD',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame([], $products);
    }

    public function testSkipsProductWithInvalidExchangeRate(): void
    {
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7696,
                'name' => 'Telecom',
                'countryId' => 192,
                'topupType' => 'RTR',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [50, 100]],
                'exchangeRate' => null,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'MAD',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame([], $products);
    }

    public function testMapsDataTopupTypeToUtilitiesInternetLikeDtoneDoesForNauta(): void
    {
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 9999,
                'name' => 'Producto Data de prueba',
                'countryId' => 192,
                'topupType' => 'Data',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [1000]],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(1, $products);
        $this->assertTrue($products[0]->isMobileOrInternetService);
        $this->assertSame(['name' => 'Utilities', 'subservice' => ['name' => 'Internet']], $products[0]->service);
    }

    public function testUnknownTopupTypeIsNotClassifiedAsMobileOrInternetService(): void
    {
        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 8023,
                'name' => 'PIN Netflix Cuba (hipotético)',
                'countryId' => 192,
                'topupType' => 'Gift Cards',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [3000]],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(1, $products);
        $this->assertFalse($products[0]->isMobileOrInternetService);
        $this->assertSame([], $products[0]->service);
    }

    public function testTruncatesDescriptionToFitTheVarchar255Column(): void
    {
        // Bug real (2026-08-09): CommunicationProduct::$description es
        // varchar(255); el productDescription real de "Cubacel Pack
        // Combos" supera esa longitud y revienta el INSERT con "value too
        // long for type character varying(255)" si no se trunca aquí.
        $longDescription = str_repeat('a', 400);

        $this->client->method('getPortfolio')->willReturn($this->portfolio([
            [
                'articleId' => 7951,
                'name' => 'Cubacel  Pack Combos',
                'countryId' => 192,
                'topupType' => 'Bundles',
                'amountType' => 'by_list',
                'saleAmount' => ['from' => null, 'to' => null, 'step' => null, 'list' => [2200]],
                'exchangeRate' => 22.0,
                'saleCurrency' => 'USD',
                'destinationCurrency' => 'CUP',
                'productDescription' => $longDescription,
            ],
        ]));

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(1, $products);
        $this->assertSame(255, mb_strlen($products[0]->description));
        $this->assertSame(str_repeat('a', 255), $products[0]->description);
    }

    public function testEmptyPortfolioYieldsNoProducts(): void
    {
        $this->client->method('getPortfolio')->willReturn([]);

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame([], $products);
    }
}
