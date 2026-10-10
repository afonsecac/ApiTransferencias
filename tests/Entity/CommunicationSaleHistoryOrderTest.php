<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationSaleHistory;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;

/**
 * Bug real (2026-10-10): el historial de una venta se mostraba fuera de
 * orden cronológico cuando dos transiciones consecutivas (p.ej.
 * Pending->Completed de un proveedor síncrono) caían en el mismo segundo de
 * reloj — communication_sale_history.updated_at es TIMESTAMP(0), sin
 * fracción de segundo, y el OrderBy original solo ordenaba por esa columna,
 * sin desempate. Dos filas con el mismo updated_at no tienen orden estable
 * garantizado en Postgres.
 *
 * @covers \App\Entity\CommunicationSaleInfo
 */
class CommunicationSaleHistoryOrderTest extends ProviderFunctionalTestCase
{
    public function testHistoricalOrdersByIdWhenUpdatedAtTies(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $sale = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setClientTransactionId('ctx-histord1')
            ->setTransactionId('histord1')
            ->setTransactionOrder('ETC-histord1')
            ->setAmount(20.0)
            ->setCurrency('USD')
            ->setTotalPrice(20.0)
            ->setState(CommunicationStateEnum::COMPLETED)
            ->setProvider('ETECSA')
            ->setPhoneNumber('5358831337');
        $this->em->persist($sale);
        $this->em->flush();

        $pending = (new CommunicationSaleHistory())->setState(CommunicationStateEnum::PENDING)->setInfo([]);
        $sale->addHistorical($pending);
        $this->em->persist($pending);
        $this->em->flush();

        $completed = (new CommunicationSaleHistory())->setState(CommunicationStateEnum::COMPLETED)->setInfo([]);
        $sale->addHistorical($completed);
        $this->em->persist($completed);
        $this->em->flush();

        // Simula la colisión real: ambas filas quedan con el mismo
        // updated_at truncado a segundo, como ocurre cuando el proveedor
        // responde de forma síncrona dentro del mismo request.
        $tiedTimestamp = new \DateTimeImmutable('2026-10-10 09:10:18');
        $pending->setUpdatedAt($tiedTimestamp);
        $completed->setUpdatedAt($tiedTimestamp);
        $this->em->flush();

        $this->assertTrue($completed->getId() > $pending->getId(), 'completed debe haberse insertado después, con id mayor');

        $this->em->clear();

        /** @var CommunicationSaleRecharge $reloaded */
        $reloaded = $this->em->getRepository(CommunicationSaleRecharge::class)->find($sale->getId());
        $ordered = $reloaded->getHistorical()->toArray();

        $this->assertCount(2, $ordered);
        // DESC por updatedAt (empatado) + id: el más reciente en insertarse
        // (completed, id mayor) debe ir primero.
        $this->assertSame(CommunicationStateEnum::COMPLETED, $ordered[0]->getState());
        $this->assertSame(CommunicationStateEnum::PENDING, $ordered[1]->getState());
    }
}
