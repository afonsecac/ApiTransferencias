<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Repository\AccountRepository;
use App\Service\Provider\ClientCatalogImportService;
use App\Service\Provider\CommunicationCatalogSyncService;
use App\Service\Etecsa\SyncResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ClientCatalogImportService
 *
 * Fase 5 de la deprecación de V1: este servicio ya no materializa ningún
 * CommunicationClientPackage — al enrutar un cliente a un proveedor ≠
 * ETECSA, solo sincroniza CommunicationProduct (insumo que también necesita
 * PackageCatalogResolver V2 para MAX+margen), por entorno.
 */
class ClientCatalogImportServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationCatalogSyncService&MockObject $catalogSyncService;
    private ClientCatalogImportService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->catalogSyncService = $this->createMock(CommunicationCatalogSyncService::class);

        $this->service = new ClientCatalogImportService(
            $this->em,
            $this->catalogSyncService,
            new NullLogger(),
        );
    }

    private function accountWithEnvironment(Client $client, Environment $environment): Account&MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    public function testDoesNothingWhenProviderIsEtecsa(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setClient(new Client());
        $routing->setProvider(CommunicationProviderEnum::ETECSA->value);

        $this->catalogSyncService->expects($this->never())->method('syncProducts');

        $this->service->importForRouting($routing);
    }

    public function testDoesNothingWhenProviderUnknown(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setClient(new Client());
        $routing->setProvider('ALGO_NO_REGISTRADO');

        $this->catalogSyncService->expects($this->never())->method('syncProducts');

        $this->service->importForRouting($routing);
    }

    public function testDoesNothingWhenClientIsMissing(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->expects($this->never())->method('syncProducts');

        $this->service->importForRouting($routing);
    }

    public function testSyncsProductsForTheRoutingEnvironmentWhenSet(): void
    {
        $client = new Client();
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->expects($this->once())
            ->method('syncProducts')
            ->with(CommunicationProviderEnum::DTONE, $environment)
            ->willReturn(new SyncResult(1, 0, 0));

        $this->service->importForRouting($routing);
    }

    public function testSyncsProductsForEveryDistinctEnvironmentAmongTheClientsActiveAccountsWhenRoutingHasNoEnvironment(): void
    {
        $client = new Client();
        $environment1 = $this->createMock(Environment::class);
        $environment1->method('getId')->willReturn(10);
        $environment2 = $this->createMock(Environment::class);
        $environment2->method('getId')->willReturn(20);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([
            $this->accountWithEnvironment($client, $environment1),
            $this->accountWithEnvironment($client, $environment2),
            // Misma tupla de entorno que la primera cuenta: no debe
            // duplicar el sync.
            $this->accountWithEnvironment($client, $environment1),
        ]);
        $this->em->method('getRepository')->with(Account::class)->willReturn($accountRepo);

        $syncedEnvironments = [];
        $this->catalogSyncService->expects($this->exactly(2))
            ->method('syncProducts')
            ->willReturnCallback(function ($provider, $environment) use (&$syncedEnvironments) {
                $syncedEnvironments[] = $environment;

                return new SyncResult(1, 0, 0);
            });

        $this->service->importForRouting($routing);

        $this->assertSame([$environment1, $environment2], $syncedEnvironments);
    }

    public function testSyncFailureIsLoggedAndDoesNotThrow(): void
    {
        $client = new Client();
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willThrowException(new \RuntimeException('DTOne inalcanzable'));

        // No debe propagar la excepción.
        $this->service->importForRouting($routing);
        $this->addToAssertionCount(1);
    }
}
