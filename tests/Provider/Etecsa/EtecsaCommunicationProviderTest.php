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
 * @covers \App\Provider\Etecsa\EtecsaCommunicationProvider::fetchPromotionProducts
 *
 * Cubre solo fetchPromotionProducts() (Fase 5C) — ETECSA no tiene concepto
 * de "promoción" propio, delega en fetchProducts() sin filtrar.
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
}
