<?php

namespace App\Tests\Functional\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Repository\ClientProviderRoutingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @covers \App\Repository\ClientProviderRoutingRepository::findActiveRouteScopesForClient
 *
 * findActiveRouteScopesForClient() devuelve una proyección ESCALAR (no
 * entidades) — ver su docblock para el porqué. Sigue siendo una lista
 * PLANA a propósito (sin filtrar por environment/saleType/categoría aquí):
 * App\Provider\ProviderDispatchResolver hace ese filtrado en PHP sobre
 * este mismo resultado — ver ProviderDispatchResolverTest para la
 * cobertura de esa parte.
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

        // uniq_cpr_scope exige (client, environment, saleType, serviceKey)
        // distinto por fila activa — se usa un saleType distinto por fila
        // para poder tener 3 proveedores activos del mismo cliente a la
        // vez, igual que en producción (cada fila real suele venir de un
        // routing por saleType/entorno, no de 3 filas "generales" idénticas).
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, saleType: 'sale', priority: 200);
        $csq = $this->createRouting($client, CommunicationProviderEnum::CSQ->value, saleType: 'recharge', priority: 0);
        $dtone = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, priority: 100);

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertSame(
            [$csq->getId(), $dtone->getId()],
            [$result[0]['id'], $result[1]['id']],
        );
        $this->assertSame(CommunicationProviderEnum::CSQ->value, $result[0]['provider']);
    }

    public function testTieBreaksBySmallestIdWhenPriorityMatches(): void
    {
        $client = $this->createClient();

        // Prioridad DEFAULT (100) para ambas — mismo escenario que deja el
        // backfill cuando ninguna fila era la "más general" del cliente.
        // saleType distinto solo para no chocar con uniq_cpr_scope.
        $first = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, saleType: 'recharge');
        $second = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'sale');

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertSame($first->getId(), $result[0]['id']);
        $this->assertSame($second->getId(), $result[1]['id']);
    }

    public function testIncludesRowsRegardlessOfEnvironmentOrSaleTypeScope(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();

        // Lista PLANA a propósito: a diferencia de findResolvedFor()
        // (admisión, precedencia por especificidad), este método no filtra
        // por scope — ProviderDispatchResolver recorre TODAS las filas
        // activas del cliente en PHP, sin importar a qué (environment,
        // saleType, categoría) estén ligadas.
        $general = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, priority: 0);
        $scoped = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, $environment, 'recharge', priority: 10);

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertCount(2, $result);
        $this->assertSame($general->getId(), $result[0]['id']);
        $this->assertSame($scoped->getId(), $result[1]['id']);
    }

    public function testProjectsFallbackProviderAndServiceCategory(): void
    {
        $client = $this->createClient();

        $routing = $this->createRouting(
            $client,
            CommunicationProviderEnum::CSQ->value,
            fallbackProvider: CommunicationProviderEnum::DTONE->value,
            serviceName: 'Mobile',
            subserviceName: 'AIRTIME',
        );

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertSame($routing->getId(), $result[0]['id']);
        $this->assertSame('DTONE', $result[0]['fallbackProvider']);
        $this->assertSame('Mobile', $result[0]['serviceName']);
        $this->assertSame('AIRTIME', $result[0]['subserviceName']);
    }

    public function testExcludesInactiveRows(): void
    {
        $client = $this->createClient();

        $active = $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, isActive: false);

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertCount(1, $result);
        $this->assertSame($active->getId(), $result[0]['id']);
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
        $this->assertCount(1, $repository->findActiveRouteScopesForClient($client->getId()));

        // saleType distinto solo para no chocar con uniq_cpr_scope.
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'sale');

        $this->assertCount(2, $repository->findActiveRouteScopesForClient($client->getId()));
    }

    public function testInvalidateCacheForcesAFreshQuery(): void
    {
        $client = $this->createClient();
        $repository = $this->repository();

        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->assertCount(1, $repository->findActiveRouteScopesForClient($client->getId()));

        $repository->invalidateCache();

        $this->assertCount(1, $repository->findActiveRouteScopesForClient($client->getId()));
    }

    // ---- uniq_cpr_scope con service_key (Version20260828120000) ----

    public function testTwoActiveRowsForTheSameScopeWithDifferentCategoriesCoexist(): void
    {
        $client = $this->createClient();

        $mobile = $this->createRouting($client, CommunicationProviderEnum::CSQ->value, serviceName: 'Mobile', subserviceName: 'AIRTIME');
        $utilities = $this->createRouting($client, CommunicationProviderEnum::DTONE->value, serviceName: 'Utilities', subserviceName: 'INTERNET');

        $result = $this->repository()->findActiveRouteScopesForClient($client->getId());

        $this->assertCount(2, $result);
        $this->assertSame(
            [$mobile->getId(), $utilities->getId()],
            array_column($result, 'id'),
        );
    }

    public function testTheDatabaseRejectsTwoActiveRowsForTheSameScopeAndCategory(): void
    {
        $client = $this->createClient();

        $this->createRouting($client, CommunicationProviderEnum::CSQ->value, serviceName: 'Mobile', subserviceName: 'AIRTIME');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, serviceName: 'Mobile', subserviceName: 'AIRTIME');
    }
}
