<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleInfoRepository;

/**
 * @covers \App\Repository\CommunicationSaleInfoRepository::getStatsTopPackages
 *
 * V2 Fase 4: una venta V2 no tiene `package` (ver
 * CommunicationSaleService::admit()) — sin el COALESCE(p.id, cp.id) /
 * COALESCE(p.name, cp.name) que se añadió a esta query, todas las ventas V2
 * se agruparían bajo un único bucket "sin paquete" en vez de desglosarse por
 * CommunicationPackage. Verificado contra Postgres real porque es
 * exactamente el tipo de query (COALESCE + GROUP BY) que un mock no puede
 * validar.
 */
class CommunicationSaleInfoRepositoryStatsTest extends ProviderFunctionalTestCase
{
    private function repository(): CommunicationSaleInfoRepository
    {
        return self::getContainer()->get(CommunicationSaleInfoRepository::class);
    }

    private function legacyRecharge(CommunicationClientPackage $package, float $price, string $transactionId): CommunicationSaleRecharge
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $recharge = (new CommunicationSaleRecharge())
            ->setPhoneNumber('53500000')
            ->setClientTransactionId('ctx-' . $transactionId)
            ->setTransactionId($transactionId)
            ->setTenant($account)
            ->setProvider('ETECSA')
            ->setAmount($price)
            ->setCurrency('USD')
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setPackage($package);
        $recharge->setPackageId($package->getId() ?? 1);
        $recharge->setTotalPrice($price);

        $this->em->persist($recharge);

        return $recharge;
    }

    private function v2Recharge(CommunicationPackage $catalogPackage, float $price, string $transactionId): CommunicationSaleRecharge
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $recharge = (new CommunicationSaleRecharge())
            ->setPhoneNumber('53500001')
            ->setClientTransactionId('ctx-' . $transactionId)
            ->setTransactionId($transactionId)
            ->setTenant($account)
            ->setProvider('CSQ')
            ->setAmount($price)
            ->setCurrency('USD')
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setCatalogPackage($catalogPackage)
            ->setDestinationAmount($catalogPackage->getDestinationAmount())
            ->setDestinationCurrency($catalogPackage->getDestinationCurrency());
        $recharge->setTotalPrice($price);

        $this->em->persist($recharge);

        return $recharge;
    }

    public function testGroupsV2SalesByCatalogPackageInsteadOfCollapsingToNull(): void
    {
        $legacyPackage = (new CommunicationClientPackage())
            ->setName('Paquete legacy')
            ->setDescription('Paquete legacy')
            ->setAmount(10.0)
            ->setCurrency('USD')
            ->setActiveEndAt(new \DateTimeImmutable('+1 year'));
        $this->em->persist($legacyPackage);
        $this->em->flush();

        $catalogPackage = (new CommunicationPackage())
            ->setName('Paquete V2')
            ->setDescription('Paquete V2')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');
        $this->em->persist($catalogPackage);
        $this->em->flush();

        $this->legacyRecharge($legacyPackage, 10.0, '2608100200001');
        $this->v2Recharge($catalogPackage, 8.5, '2608100200002');
        $this->v2Recharge($catalogPackage, 8.5, '2608100200003');
        $this->em->flush();

        $stats = $this->repository()->getStatsTopPackages(null, null, null, null, null, 10);

        $byName = [];
        foreach ($stats as $row) {
            $byName[$row['packageName']] = $row;
        }

        $this->assertArrayHasKey('Paquete legacy', $byName);
        $this->assertSame(1, $byName['Paquete legacy']['total']);
        $this->assertEqualsWithDelta(10.0, $byName['Paquete legacy']['totalAmount'], 0.001);

        $this->assertArrayHasKey('Paquete V2', $byName);
        $this->assertSame($catalogPackage->getId(), $byName['Paquete V2']['packageId']);
        $this->assertSame(2, $byName['Paquete V2']['total']);
        $this->assertEqualsWithDelta(17.0, $byName['Paquete V2']['totalAmount'], 0.001);

        // Sin el COALESCE, ambas ventas V2 (package NULL) se habrían
        // agrupado bajo una sola fila con packageName NULL en vez de bajo
        // "Paquete V2" — exactamente 2 filas (una por paquete) es lo que
        // prueba que el fix funciona.
        $this->assertCount(2, $stats);
    }
}
