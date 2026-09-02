<?php

namespace App\Tests\Provider\Etecsa;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\PromotionCatalogQuery;
use App\Provider\Etecsa\EtecsaCommunicationProvider;
use App\Provider\Etecsa\EtecsaStatusMapper;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Etecsa\EtecsaCommunicationProvider::fetchProducts
 * @covers \App\Provider\Etecsa\EtecsaCommunicationProvider::fetchPromotionProducts
 *
 * fetchPromotionProducts() (Fase 5C) delega en fetchProducts() sin filtrar
 * — ETECSA no tiene concepto de "promoción" propio. Los casos de
 * fetchProducts() de abajo cierran el hueco de cobertura directa que tenía
 * este proveedor frente a CSQ/DTOne (que sí prueban productTypeRaw,
 * wholesalePrice, validFrom/validTo y el guard de items sin Id).
 */
class EtecsaCommunicationProviderTest extends TestCase
{
    private EtecsaGatewayClient&MockObject $client;
    private EnvironmentRepository&MockObject $environmentRepository;
    private EtecsaCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EtecsaGatewayClient::class);
        $this->environmentRepository = $this->createMock(EnvironmentRepository::class);
        $this->provider = new EtecsaCommunicationProvider($this->client, new EtecsaStatusMapper(), $this->environmentRepository);
    }

    public function testFetchPromotionProductsDelegatesToFetchProductsWithoutFiltering(): void
    {
        $environment = $this->createMock(Environment::class);
        $this->environmentRepository->method('find')->with(4)->willReturn($environment);

        $this->client->method('listPackages')->with($environment)->willReturn([
            ['Id' => 100, 'Description' => 'Producto genérico', 'Enabled' => true],
        ]);

        $context = new ProviderContext(provider: CommunicationProviderEnum::ETECSA, environmentType: 'TEST', environmentId: 4);
        $query = new PromotionCatalogQuery(
            destinationCurrency: 'CUP',
            destinationAmounts: [500.0],
            activeFrom: new \DateTimeImmutable('2026-08-18T00:00:00+00:00'),
            activeTo: new \DateTimeImmutable('2026-08-25T23:59:00+00:00'),
        );

        $products = iterator_to_array($this->provider->fetchPromotionProducts($context, $query));

        $this->assertCount(1, $products);
        $this->assertSame('100', $products[0]->externalId);
        // Producto de monto flexible — igual que fetchProducts() normal.
        $this->assertNull($products[0]->destinationAmount);
    }

    public function testFetchProductsMapsTypeWholesalePriceAndValidityWindow(): void
    {
        $environment = $this->createMock(Environment::class);
        $this->environmentRepository->method('find')->with(4)->willReturn($environment);

        $this->client->method('listPackages')->with($environment)->willReturn([
            [
                'Id' => 200,
                'Description' => 'Recarga Cubacel',
                'PackageType' => 'RECHARGE',
                'Price' => 150.5,
                'Enabled' => true,
                'InitialDate' => '2026-08-18T00:00:00+00:00',
                'FinalDate' => '2026-08-25T23:59:00+00:00',
            ],
        ]);

        $context = new ProviderContext(provider: CommunicationProviderEnum::ETECSA, environmentType: 'TEST', environmentId: 4);
        $products = iterator_to_array($this->provider->fetchProducts($context));

        $this->assertCount(1, $products);
        $product = $products[0];
        $this->assertSame('200', $product->externalId);
        $this->assertSame('Recarga Cubacel', $product->name);
        $this->assertSame('Recarga Cubacel', $product->description);
        $this->assertSame('RECHARGE', $product->productTypeRaw);
        $this->assertSame(150.5, $product->wholesalePrice);
        $this->assertTrue($product->enabled);
        $this->assertEquals(new \DateTimeImmutable('2026-08-18T00:00:00+00:00'), $product->validFrom);
        $this->assertEquals(new \DateTimeImmutable('2026-08-25T23:59:00+00:00'), $product->validTo);
        $this->assertTrue($product->isMobileOrInternetService);
        $this->assertSame(['name' => 'MOBILE', 'subservice' => ['name' => 'AIRTIME']], $product->service);
    }

    public function testFetchProductsDefaultsDisabledAndUnboundedValidityWhenFieldsAreMissing(): void
    {
        $environment = $this->createMock(Environment::class);
        $this->environmentRepository->method('find')->with(4)->willReturn($environment);

        // Sin Enabled/InitialDate/FinalDate/PackageType/Price — el mapeo no
        // debe romper, solo caer a sus defaults (false/null/''/0.0).
        $this->client->method('listPackages')->with($environment)->willReturn([
            ['Id' => 300, 'Description' => 'Sin metadata extra'],
        ]);

        $context = new ProviderContext(provider: CommunicationProviderEnum::ETECSA, environmentType: 'TEST', environmentId: 4);
        $products = iterator_to_array($this->provider->fetchProducts($context));

        $this->assertCount(1, $products);
        $product = $products[0];
        $this->assertSame('', $product->productTypeRaw);
        $this->assertSame(0.0, $product->wholesalePrice);
        $this->assertFalse($product->enabled);
        $this->assertNull($product->validFrom);
        $this->assertNull($product->validTo);
    }

    public function testFetchProductsSkipsItemsWithoutId(): void
    {
        $environment = $this->createMock(Environment::class);
        $this->environmentRepository->method('find')->with(4)->willReturn($environment);

        $this->client->method('listPackages')->with($environment)->willReturn([
            ['Description' => 'Sin Id, debe ignorarse'],
            ['Id' => 400, 'Description' => 'Con Id, sí se mapea'],
        ]);

        $context = new ProviderContext(provider: CommunicationProviderEnum::ETECSA, environmentType: 'TEST', environmentId: 4);
        $products = iterator_to_array($this->provider->fetchProducts($context));

        $this->assertCount(1, $products);
        $this->assertSame('400', $products[0]->externalId);
    }
}
