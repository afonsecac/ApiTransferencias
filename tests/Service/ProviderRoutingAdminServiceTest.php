<?php

namespace App\Tests\Service;

use App\DTO\CreateProviderRoutingDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ClientCatalogImportService;
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
    private ClientCatalogImportService&MockObject $catalogImportService;
    private ProviderCredentialsResolver $credentialsResolver;
    private SysConfigRepository&MockObject $credentialsSysConfigRepo;
    private ProviderRoutingAdminService $service;

    /**
     * Proveedor de prueba sin campos de configuración obligatorios — así
     * isFullyConfigured() siempre da true para él, sin importar qué
     * devuelva sys_config. Usado por defecto en los tests que no son sobre
     * el gate de habilitación (para no tener que preocuparse por eso).
     */
    private function fakeProviderWithoutRequiredFields(CommunicationProviderEnum $code): CommunicationProviderInterface
    {
        return new class($code) implements CommunicationProviderInterface {
            public function __construct(private readonly CommunicationProviderEnum $code)
            {
            }

            public function getCode(): CommunicationProviderEnum
            {
                return $this->code;
            }

            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::RECHARGE];
            }

            public function getConfigSchema(): array
            {
                return [];
            }
        };
    }

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->catalogImportService = $this->createMock(ClientCatalogImportService::class);

        // ProviderCredentialsResolver es `final`: se instancia real. Por
        // defecto se registran ETECSA/DTONE sin campos obligatorios, así
        // isFullyConfigured() siempre da true — los tests del gate de
        // habilitación reconstruyen su propio resolver con un esquema real.
        $this->credentialsSysConfigRepo = $this->createMock(SysConfigRepository::class);
        $registry = new ProviderRegistry([
            $this->fakeProviderWithoutRequiredFields(CommunicationProviderEnum::ETECSA),
            $this->fakeProviderWithoutRequiredFields(CommunicationProviderEnum::DTONE),
        ]);
        $this->credentialsResolver = new ProviderCredentialsResolver($this->credentialsSysConfigRepo, $registry);

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
            $this->catalogImportService,
            $this->credentialsResolver,
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

    public function testCreatePersistsTheServiceCategoryAndDerivesServiceKey(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->stubClientAndFreeScope($client);
        $this->security->method('getUser')->willReturn(null);

        $routing = $this->service->create(new CreateProviderRoutingDto(
            clientId: 1,
            provider: 'DTONE',
            serviceName: 'Mobile',
            subserviceName: 'AIRTIME',
        ));

        $this->assertSame('Mobile', $routing->getServiceName());
        $this->assertSame('AIRTIME', $routing->getSubserviceName());
        $this->assertSame('Mobile|AIRTIME', $routing->getServiceKey());
    }

    /**
     * Dos filas activas del mismo cliente/entorno/tipo de venta pueden
     * coexistir si son de categorías distintas — assertScopeIsFree() ahora
     * filtra también por serviceKey, así que la query de "¿existe otra
     * fila?" con una categoría distinta debe volver vacía.
     */
    public function testCreateAllowsTwoActiveRowsForTheSameScopeWithDifferentCategories(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->stubClientAndFreeScope($client);
        $this->security->method('getUser')->willReturn(null);

        $routing = $this->service->create(new CreateProviderRoutingDto(
            clientId: 1,
            provider: 'DTONE',
            serviceName: 'Utilities',
            subserviceName: 'INTERNET',
        ));

        $this->assertSame('Utilities|INTERNET', $routing->getServiceKey());
    }

    // ---- gate de habilitación (assertProviderIsEnabled) ----

    /**
     * Proveedor de prueba con UN campo obligatorio ('api_key'), para poder
     * controlar isFullyConfigured() vía el mock de sys_config.
     */
    private function fakeProviderRequiringApiKey(CommunicationProviderEnum $code): CommunicationProviderInterface
    {
        return new class($code) implements CommunicationProviderInterface {
            public function __construct(private readonly CommunicationProviderEnum $code)
            {
            }

            public function getCode(): CommunicationProviderEnum
            {
                return $this->code;
            }

            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::RECHARGE];
            }

            public function getConfigSchema(): array
            {
                return [new \App\Provider\Contract\ProviderConfigField('api_key', 'API key', required: true, secret: true)];
            }
        };
    }

    /**
     * Reconstruye el servicio con un resolver real donde DTONE requiere
     * 'api_key' y sys_config devuelve $apiKeyConfiguredFor por entorno.
     *
     * @param list<string> $apiKeyConfiguredFor entornos ('TEST'/'PROD') donde el api_key SÍ está configurado
     */
    private function serviceWithDtoneRequiringApiKey(array $apiKeyConfiguredFor): ProviderRoutingAdminService
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnCallback(
            function (string $key) use ($apiKeyConfiguredFor) {
                foreach (['TEST', 'PROD'] as $envType) {
                    if ($key === 'provider.dtone.' . strtolower($envType) . '.api_key') {
                        return in_array($envType, $apiKeyConfiguredFor, true) ? 'configured-key' : null;
                    }
                }

                return null;
            }
        );

        $registry = new ProviderRegistry([$this->fakeProviderRequiringApiKey(CommunicationProviderEnum::DTONE)]);
        $credentialsResolver = new ProviderCredentialsResolver($sysConfigRepo, $registry);

        return new ProviderRoutingAdminService(
            $this->em,
            $this->routingRepo,
            $this->providerResolver,
            $this->security,
            $this->catalogImportService,
            $credentialsResolver,
        );
    }

    /**
     * Deja el repo de Client + la query de scope (sin conflicto) listos, tal
     * como testCreatePersistsRoutingAndInvalidatesCache — necesario porque
     * assertScopeIsFree() corre ANTES que el gate de habilitación.
     */
    private function stubClientAndFreeScope(Client&MockObject $client): void
    {
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
    }

    public function testCreateThrowsWhenProviderNotFullyConfiguredForTargetEnvironment(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->stubClientAndFreeScope($client);

        $service = $this->serviceWithDtoneRequiringApiKey(apiKeyConfiguredFor: []); // ninguno configurado

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no tiene configuradas sus claves obligatorias');

        // environmentId=null: sin Environment que resolver, el gate exige TEST y PROD.
        $service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE'));
    }

    public function testCreateChecksBothEnvironmentsWhenEnvironmentIdIsNull(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->stubClientAndFreeScope($client);

        // Configurado en TEST pero no en PROD — con environmentId=null se exigen ambos.
        $service = $this->serviceWithDtoneRequiringApiKey(apiKeyConfiguredFor: ['TEST']);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('PROD');

        $service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE'));
    }

    public function testCreateSucceedsWhenProviderIsFullyConfigured(): void
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

        $service = $this->serviceWithDtoneRequiringApiKey(apiKeyConfiguredFor: ['TEST', 'PROD']);

        $routing = $service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE'));

        $this->assertSame('DTONE', $routing->getProvider());
    }

    public function testUpdateThrowsWhenChangingToNotFullyConfiguredProvider(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setProvider('ETECSA');

        $service = $this->serviceWithDtoneRequiringApiKey(apiKeyConfiguredFor: []);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no tiene configuradas sus claves obligatorias');

        $service->update($routing, new UpdateProviderRoutingDto(provider: 'DTONE'));
    }

    public function testUpdateDoesNotCheckGateWhenProviderIsUnchanged(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setProvider('DTONE');

        $this->em->expects($this->once())->method('flush');

        // DTONE sin api_key configurado, pero como el provider NO cambia
        // (sigue siendo DTONE), el gate no debe dispararse.
        $service = $this->serviceWithDtoneRequiringApiKey(apiKeyConfiguredFor: []);

        $updated = $service->update($routing, new UpdateProviderRoutingDto(notes: 'solo actualizo la nota'));

        $this->assertSame('solo actualizo la nota', $updated->getNotes());
    }

    /**
     * Igual que serviceWithDtoneRequiringApiKey pero api_key SIEMPRE
     * configurado (aísla el chequeo del interruptor manual `active` del
     * chequeo de isFullyConfigured()).
     *
     * @param list<string> $inactiveFor entornos ('TEST'/'PROD') marcados como inactivos a mano
     */
    private function serviceWithDtoneConfiguredButInactiveFor(array $inactiveFor): ProviderRoutingAdminService
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnCallback(
            function (string $key) use ($inactiveFor) {
                foreach (['TEST', 'PROD'] as $envType) {
                    $lower = strtolower($envType);
                    if ($key === "provider.dtone.{$lower}.api_key") {
                        return 'configured-key';
                    }
                    if ($key === "provider.dtone.{$lower}.active") {
                        return in_array($envType, $inactiveFor, true) ? '0' : null;
                    }
                }

                return null;
            }
        );

        $registry = new ProviderRegistry([$this->fakeProviderRequiringApiKey(CommunicationProviderEnum::DTONE)]);
        $credentialsResolver = new ProviderCredentialsResolver($sysConfigRepo, $registry);

        return new ProviderRoutingAdminService(
            $this->em,
            $this->routingRepo,
            $this->providerResolver,
            $this->security,
            $this->catalogImportService,
            $credentialsResolver,
        );
    }

    public function testCreateThrowsWhenProviderIsManuallyDeactivatedEvenIfFullyConfigured(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->stubClientAndFreeScope($client);

        $service = $this->serviceWithDtoneConfiguredButInactiveFor(inactiveFor: ['TEST', 'PROD']);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('está desactivado manualmente');

        $service->create(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE'));
    }

    public function testUpdateThrowsWhenChangingToAManuallyDeactivatedProvider(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setProvider('ETECSA');

        $service = $this->serviceWithDtoneConfiguredButInactiveFor(inactiveFor: ['TEST', 'PROD']);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('está desactivado manualmente');

        $service->update($routing, new UpdateProviderRoutingDto(provider: 'DTONE'));
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

    public function testUpdateChangesTheServiceCategoryAndRevalidatesScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');

        $this->stubScopeQuery(null);

        $updated = $this->service->update($routing, new UpdateProviderRoutingDto(serviceName: 'Mobile', subserviceName: 'AIRTIME'));

        $this->assertSame('Mobile', $updated->getServiceName());
        $this->assertSame('AIRTIME', $updated->getSubserviceName());
        $this->assertSame('Mobile|AIRTIME', $updated->getServiceKey());
    }

    /**
     * '' es la convención de "volver a comodín" (null = no tocar) — ver
     * docblock de UpdateProviderRoutingDto.
     */
    public function testUpdateWithEmptyStringClearsTheServiceCategoryBackToWildcard(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');
        $routing->setServiceCategory('Mobile', 'AIRTIME');

        $this->stubScopeQuery(null);

        $updated = $this->service->update($routing, new UpdateProviderRoutingDto(serviceName: ''));

        $this->assertNull($updated->getServiceName());
        $this->assertNull($updated->getSubserviceName());
        $this->assertSame('|', $updated->getServiceKey());
    }

    public function testUpdateWithoutTouchingCategoryPreservesTheExistingOne(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider('ETECSA');
        $routing->setServiceCategory('Mobile', 'AIRTIME');

        $this->stubScopeQuery(null);

        // scopeTouched se activa por saleType, no por categoría — la
        // categoría existente debe sobrevivir intacta.
        $updated = $this->service->update($routing, new UpdateProviderRoutingDto(saleType: 'recharge'));

        $this->assertSame('Mobile', $updated->getServiceName());
        $this->assertSame('AIRTIME', $updated->getSubserviceName());
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
