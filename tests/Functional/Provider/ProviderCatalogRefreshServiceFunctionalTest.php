<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Enums\CommunicationProviderEnum;
use App\Service\Etecsa\SyncResult;
use App\Service\Provider\CommunicationCatalogSyncService;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ProviderCatalogRefreshService;
use App\Service\Provider\ResolvedProductPrice;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ProviderCatalogRefreshService
 *
 * Funcional contra Postgres real: verifica lo más delicado del diseño —
 * que un CommunicationPricePackage cuyo producto NO cambió queda
 * verdaderamente intacto (ni siquiera su updatedAt se toca, a pesar del
 * callback #[ORM\PreFlush] que de otro modo lo haría), que uno
 * autoManaged=true SÍ se actualiza cuando su producto cambia, y que uno
 * NO autoManaged nunca se toca aunque su producto cambie.
 *
 * CommunicationCatalogSyncService se mockea (no hay credenciales DTOne
 * reales en este entorno) pero simula un sync real modificando la fila en
 * la BD directamente — todo lo demás (las consultas escalares, la
 * hidratación selectiva, la propagación) corre contra Postgres real.
 */
class ProviderCatalogRefreshServiceFunctionalTest extends ProviderFunctionalTestCase
{
    public function testOnlyUpdatesAutoManagedPackagesOfProductsThatActuallyChanged(): void
    {
        $client = $this->createClient();
        $client->setCurrency('EUR');
        $this->em->flush();

        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        // Producto A: cambiará de precio durante el "sync" simulado.
        $productA = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(1001)
            ->setPackageType('FIXED_VALUE_RECHARGE')
            ->setPrice(4.5)
            ->setPriceCurrency('USD')
            ->setDestinationAmount(5.0)
            ->setDestinationUnit('CUP')
            ->setEnabled(true)
            ->setDescription('Producto A')
            ->setProvider(CommunicationProviderEnum::DTONE->value)
            ->setExternalRef('ext-a');

        // Producto B: NO cambiará.
        $productB = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(1002)
            ->setPackageType('FIXED_VALUE_RECHARGE')
            ->setPrice(9.0)
            ->setPriceCurrency('USD')
            ->setDestinationAmount(10.0)
            ->setDestinationUnit('CUP')
            ->setEnabled(true)
            ->setDescription('Producto B')
            ->setProvider(CommunicationProviderEnum::DTONE->value)
            ->setExternalRef('ext-b');

        $this->em->persist($productA);
        $this->em->persist($productB);
        $this->em->flush();

        $autoManagedA = $this->pricePackageFor($productA, $account, autoManaged: true);
        $autoManagedB = $this->pricePackageFor($productB, $account, autoManaged: true);
        $manualA = $this->pricePackageFor($productA, $account, autoManaged: false);

        $this->em->flush();

        $autoManagedAId = $autoManagedA->getId();
        $autoManagedBId = $autoManagedB->getId();
        $manualAId = $manualA->getId();
        $autoManagedBUpdatedAtBefore = $autoManagedB->getUpdatedAt();
        $manualAUpdatedAtBefore = $manualA->getUpdatedAt();
        $productAId = $productA->getId();

        // Simula que el sync real cambió el costo mayorista de A (DTOne subió el precio).
        $catalogSyncService = $this->createMock(CommunicationCatalogSyncService::class);
        $catalogSyncService->method('syncProducts')->willReturnCallback(
            function () use ($productAId) {
                $conn = $this->em->getConnection();
                $conn->executeStatement(
                    'UPDATE communication_product SET price = 6.0 WHERE id = :id',
                    ['id' => $productAId],
                );

                return new SyncResult(0, 1, 0);
            }
        );

        $priceResolver = $this->createMock(ProductPriceResolver::class);
        $priceResolver->method('resolve')->willReturn(new ResolvedProductPrice(5.34, 'EUR', 0.89, new \DateTimeImmutable('2026-07-31'), null));

        $refreshService = new ProviderCatalogRefreshService($this->em, $catalogSyncService, $priceResolver, new NullLogger());
        $result = $refreshService->refreshAll();

        $this->assertSame(1, $result->pairsProcessed);
        $this->assertSame(0, $result->pairsFailed);
        $this->assertSame(1, $result->productsChanged);
        $this->assertSame(1, $result->pricePackagesUpdated);

        // refreshAll() hace $em->clear() internamente (ver
        // ProviderCatalogRefreshService): las instancias locales quedan
        // detached, hay que releerlas por id para ver el estado real.
        $autoManagedA = $this->em->find(CommunicationPricePackage::class, $autoManagedAId);
        $autoManagedB = $this->em->find(CommunicationPricePackage::class, $autoManagedBId);
        $manualA = $this->em->find(CommunicationPricePackage::class, $manualAId);

        $this->assertSame(5.34, $autoManagedA->getAmount());
        $this->assertSame('EUR', $autoManagedA->getCurrency());
        $this->assertSame(0.89, $autoManagedA->getConversionRate());
        $this->assertSame(6.0, $autoManagedA->getPrice());

        // B no cambió: ni el valor ni updatedAt deben haberse tocado. La
        // columna es timestamp(0) (sin fracción de segundo) — se compara
        // formateado para no fallar por precisión de microsegundos entre la
        // instancia en memoria (antes del flush) y la releída de la BD.
        $this->assertSame(9.0, $autoManagedB->getPrice());
        $this->assertSame(
            $autoManagedBUpdatedAtBefore->format('Y-m-d H:i:s'),
            $autoManagedB->getUpdatedAt()->format('Y-m-d H:i:s'),
        );

        // manualA depende de A (que sí cambió), pero NO es autoManaged: nunca se toca.
        $this->assertSame(4.5, $manualA->getPrice());
        $this->assertSame(
            $manualAUpdatedAtBefore->format('Y-m-d H:i:s'),
            $manualA->getUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    private function pricePackageFor(CommunicationProduct $product, Account $account, bool $autoManaged): CommunicationPricePackage
    {
        $pricePackage = (new CommunicationPricePackage())
            ->setProduct($product)
            ->setTenant($account)
            ->setEnvironment($account->getEnvironment())
            ->setName('Paquete de prueba')
            ->setDescription('Paquete de prueba')
            ->setPrice($product->getPrice())
            ->setPriceCurrency($product->getPriceCurrency())
            ->setAmount($product->getPrice())
            ->setCurrency($product->getPriceCurrency());

        if ($autoManaged) {
            $pricePackage->markAutoManaged();
        }

        $this->em->persist($pricePackage);

        return $pricePackage;
    }
}
