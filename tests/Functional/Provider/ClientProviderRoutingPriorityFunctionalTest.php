<?php

namespace App\Tests\Functional\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Repository\ClientProviderRoutingRepository;

/**
 * @covers \App\Repository\ClientProviderRoutingRepository::findActiveProvidersOrderedForClient
 *
 * V2 Fase 1: nuevo método de orden por prioridad — lo consumirá
 * ProviderDispatchResolver (Fase 2) para probar proveedores en orden hasta
 * que uno acepte la venta. Ningún flujo de despacho actual lo usa todavía
 * (ver Version20260810120200), así que este test cubre solo el
 * repositorio en sí.
 */
class ClientProviderRoutingPriorityFunctionalTest extends ProviderFunctionalTestCase
{
    private function repository(): ClientProviderRoutingRepository
    {
        return self::getContainer()->get(ClientProviderRoutingRepository::class);
    }

    public function testOrdersByPriorityAscending(): void
    {
        $client = $this->createClient();

        // uniq_cpr_scope exige (client, environment, saleType) distinto por
        // fila activa — se usa un saleType distinto por fila para poder
        // tener 3 proveedores activos del mismo cliente a la vez, igual que
        // en producción (cada fila real suele venir de un routing por
        // saleType/entorno, no de 3 filas "generales" idénticas).
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, saleType: 'sale', priority: 200);
        $csq = $this->createRouting($client, CommunicationProviderEnum::CSQ->value, saleType: 'recharge', priority: 0);
        $dtone = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, priority: 100);

        $result = $this->repository()->findActiveProvidersOrderedForClient($client->getId());

        $this->assertSame(
            [$csq->getId(), $dtone->getId()],
            [$result[0]->getId(), $result[1]->getId()],
        );
        $this->assertSame(CommunicationProviderEnum::CSQ->value, $result[0]->getProvider());
    }

    public function testTieBreaksBySmallestIdWhenPriorityMatches(): void
    {
        $client = $this->createClient();

        // Prioridad DEFAULT (100) para ambas — mismo escenario que deja el
        // backfill cuando ninguna fila era la "más general" del cliente.
        // saleType distinto solo para no chocar con uniq_cpr_scope.
        $first = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, saleType: 'recharge');
        $second = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'sale');

        $result = $this->repository()->findActiveProvidersOrderedForClient($client->getId());

        $this->assertSame($first->getId(), $result[0]->getId());
        $this->assertSame($second->getId(), $result[1]->getId());
    }

    public function testIncludesRowsRegardlessOfEnvironmentOrSaleTypeScope(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();

        // Lista PLANA a propósito: a diferencia de findResolvedFor()
        // (admisión, precedencia por especificidad), este método no filtra
        // por scope — ProviderDispatchResolver recorre TODAS las filas
        // activas del cliente en orden de prioridad, sin importar a qué
        // (environment, saleType) estén ligadas.
        $general = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, priority: 0);
        $scoped = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, $environment, 'recharge', priority: 10);

        $result = $this->repository()->findActiveProvidersOrderedForClient($client->getId());

        $this->assertCount(2, $result);
        $this->assertSame($general->getId(), $result[0]->getId());
        $this->assertSame($scoped->getId(), $result[1]->getId());
    }

    public function testExcludesInactiveRows(): void
    {
        $client = $this->createClient();

        $active = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, isActive: false);

        $result = $this->repository()->findActiveProvidersOrderedForClient($client->getId());

        $this->assertCount(1, $result);
        $this->assertSame($active->getId(), $result[0]->getId());
    }

    public function testCacheIsAutomaticallyInvalidatedWhenARoutingRowChanges(): void
    {
        // ProviderRoutingCacheListener invalida la caché 'provider_routing'
        // en postPersist/postUpdate/postRemove de CUALQUIER
        // ClientProviderRouting (ver src/EventListener/ProviderRoutingCacheListener.php)
        // — no hace falta llamar invalidateCache() a mano tras crear una
        // fila nueva, a diferencia de si esta caché no tuviera ese listener.
        $client = $this->createClient();
        $repository = $this->repository();

        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->assertCount(1, $repository->findActiveProvidersOrderedForClient($client->getId()));

        // saleType distinto solo para no chocar con uniq_cpr_scope.
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'sale');

        $this->assertCount(2, $repository->findActiveProvidersOrderedForClient($client->getId()));
    }

    public function testInvalidateCacheForcesAFreshQuery(): void
    {
        $client = $this->createClient();
        $repository = $this->repository();

        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->assertCount(1, $repository->findActiveProvidersOrderedForClient($client->getId()));

        $repository->invalidateCache();

        $this->assertCount(1, $repository->findActiveProvidersOrderedForClient($client->getId()));
    }
}
