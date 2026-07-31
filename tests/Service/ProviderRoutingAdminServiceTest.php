<?php

namespace App\Tests\Service;

use App\DTO\CreateProviderRoutingDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use App\Service\ProviderRoutingAdminService;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\Service\ProviderRoutingAdminService
 */
class ProviderRoutingAdminServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private ProviderResolver $providerResolver;
    private Security&MockObject $security;
    private ProviderRoutingAdminService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->security = $this->createMock(Security::class);

        // ProviderResolver es `final`: se instancia real con sus propias
        // dependencias mockeadas en vez de doblar la clase (ya tiene su
        // propia suite exhaustiva en ProviderResolverTest).
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->providerResolver = new ProviderResolver($sysConfigRepo, $this->routingRepo, $logger);

        $this->service = new ProviderRoutingAdminService(
            $this->em,
            $this->routingRepo,
            $this->providerResolver,
            $this->security,
        );
    }

    /**
     * Mockea `$this->em->getRepository(ClientProviderRouting::class)->createQueryBuilder('cpr')`
     * de modo que assertScopeIsFree() reciba $existing como resultado.
     */
    private function stubScopeQuery(?ClientProviderRouting $existing): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($existing);
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')
            ->willReturnCallback(function (string $class) use ($repo) {
                if ($class === ClientProviderRouting::class) {
                    return $repo;
                }

                return $this->createMock(EntityRepository::class);
            });
    }

    // ---- create ----

    public function testCreateThrowsWhenClientNotFound(): void
    {
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $clientRepo],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Cliente no encontrado');

        $this->service->create(new CreateProviderRoutingDto(clientId: 999, provider: 'DTONE'));
    }

    public function testCreateThrowsWhenEnvironmentNotFound(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $environmentRepo = $this->createMock(EntityRepository::class);
        $environmentRepo->method('find')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $clientRepo],
            [Environment::class, $environmentRepo],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Entorno no encontrado');

        $this->service->create(new CreateProviderRoutingDto(clientId: 1, environmentId: 999, provider: 'DTONE'));
    }

    public function testCreateThrowsWhenScopeAlreadyTaken(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $this->em->method('getRepository')
            ->willReturnCallback(function (string $class) use ($clientRepo) {
                if ($class === Client::class) {
                    return $clientRepo;
                }

                $qb = $this->createMock(QueryBuilder::class);
                $qb->method('andWhere')->willReturnSelf();
                $qb->method('setParameter')->willReturnSelf();
                $query = $this->createMock(Query::class);
                $query->method('getOneOrNullResult')->willReturn($this->createMock(ClientProviderRouting::class));
                $qb->method('getQuery')->willReturn($query);
                $repo = $this->createMock(EntityRepository::class);
                $repo->method('createQueryBuilder')->willReturn($qb);

                return $repo;
            });

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Ya existe un enrutado activo');

        $this->service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE'));
    }

    public function testCreatePersistsRoutingAndInvalidatesCache(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $this->em->method('getRepository')
            ->willReturnCallback(function (string $class) use ($clientRepo) {
                if ($class === Client::class) {
                    return $clientRepo;
                }

                $qb = $this->createMock(QueryBuilder::class);
                $qb->method('andWhere')->willReturnSelf();
                $qb->method('setParameter')->willReturnSelf();
                $query = $this->createMock(Query::class);
                $query->method('getOneOrNullResult')->willReturn(null);
                $qb->method('getQuery')->willReturn($query);
                $repo = $this->createMock(EntityRepository::class);
                $repo->method('createQueryBuilder')->willReturn($qb);

                return $repo;
            });

        $this->security->method('getUser')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(ClientProviderRouting::class));
        $this->em->expects($this->once())->method('flush');
        $this->routingRepo->expects($this->once())->method('invalidateCache');

        $routing = $this->service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE', notes: 'AWS Malta a DTOne'));

        $this->assertSame('DTONE', $routing->getProvider());
        $this->assertSame($client, $routing->getClient());
        $this->assertSame('AWS Malta a DTOne', $routing->getNotes());
    }

    // ---- update ----

    public function testUpdateChangesProviderWithoutTouchingScope(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setProvider('ETECSA');

        $this->em->expects($this->once())->method('flush');
        $this->routingRepo->expects($this->once())->method('invalidateCache');

        $updated = $this->service->update($routing, new UpdateProviderRoutingDto(provider: 'DTONE'));

        $this->assertSame('DTONE', $updated->getProvider());
    }

    public function testUpdateThrowsWhenReassigningToTakenScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');

        $this->stubScopeQuery($this->createMock(ClientProviderRouting::class));

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Ya existe un enrutado activo');

        $this->service->update($routing, new UpdateProviderRoutingDto(saleType: 'recharge'));
    }

    // ---- toggle ----

    public function testToggleFromInactiveToActiveChecksScopeIsFreeAndActivates(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');
        $routing->setIsActive(false);

        $this->stubScopeQuery(null);

        $this->em->expects($this->once())->method('flush');
        $this->routingRepo->expects($this->once())->method('invalidateCache');

        $toggled = $this->service->toggle($routing);

        $this->assertTrue($toggled->isActive());
    }

    public function testToggleThrowsWhenActivatingIntoTakenScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');
        $routing->setIsActive(false);

        $this->stubScopeQuery($this->createMock(ClientProviderRouting::class));

        $this->expectException(MyCurrentException::class);

        $this->service->toggle($routing);
    }

    public function testToggleFromActiveToInactiveNeverChecksScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');
        $routing->setIsActive(true);

        $this->em->expects($this->never())->method('getRepository');
        $this->em->expects($this->once())->method('flush');

        $toggled = $this->service->toggle($routing);

        $this->assertFalse($toggled->isActive());
    }

    // ---- delete ----

    public function testDeleteRemovesAndInvalidatesCache(): void
    {
        $routing = new ClientProviderRouting();

        $this->em->expects($this->once())->method('remove')->with($routing);
        $this->em->expects($this->once())->method('flush');
        $this->routingRepo->expects($this->once())->method('invalidateCache');

        $this->service->delete($routing);
    }

    // ---- preview ----

    public function testPreviewReturnsCurrentAndProposedProviderWithPendingSalesCount(): void
    {
        $client = $this->createMock(Client::class);
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $this->routingRepo->method('findResolvedFor')->with(1, null, null)->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('3');
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $clientRepo],
        ]);

        $preview = $this->service->preview(1, null, null, 'DTONE');

        $this->assertSame('ETECSA', $preview->currentEffectiveProvider);
        $this->assertSame('DTONE', $preview->proposedEffectiveProvider);
        $this->assertSame(3, $preview->pendingSalesCount);
    }

    public function testPreviewThrowsWhenClientNotFound(): void
    {
        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $clientRepo],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Cliente no encontrado');

        $this->service->preview(999, null, null, 'DTONE');
    }
}
