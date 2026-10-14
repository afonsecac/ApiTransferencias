<?php

namespace App\Tests\Controller;

use App\Controller\DashboardClientSecurityController;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\User;
use App\Service\AccountSecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @covers \App\Controller\DashboardClientSecurityController
 */
class DashboardClientSecurityControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AccountSecurityService&MockObject $accountSecurityService;
    private DashboardClientSecurityController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->accountSecurityService = $this->createMock(AccountSecurityService::class);

        $this->controller = new DashboardClientSecurityController(
            $this->em,
            $this->accountSecurityService,
        );
    }

    /**
     * Monta un container falso que responde a getUser()/isGranted(), que
     * AbstractController resuelve vía 'security.token_storage' y
     * 'security.authorization_checker'.
     */
    private function authenticateAs(?User $user, bool $isAdmin): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($user === null ? null : $token);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn($isAdmin);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            fn (string $id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true)
        );
        $container->method('get')->willReturnMap([
            ['security.token_storage', 1, $tokenStorage],
            ['security.authorization_checker', 1, $authChecker],
        ]);

        $this->controller->setContainer($container);
    }

    private function accountRepoReturningEmpty(): void
    {
        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('findBy')->willReturn([]);
        $this->em->method('getRepository')->willReturnMap([
            [Account::class, $accountRepo],
        ]);
    }

    public function testListReturnsUnauthorizedWhenNoUser(): void
    {
        $this->authenticateAs(null, false);

        $response = $this->controller->list();

        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * Regresión: el admin debe ver también los clientes desactivados para
     * poder gestionarlos/reactivarlos — antes findBy(['isActive' => true])
     * los ocultaba por completo, sin forma de volver a activarlos desde el
     * dashboard.
     */
    public function testListForAdminIncludesInactiveClients(): void
    {
        $admin = $this->createMock(User::class);
        $this->authenticateAs($admin, true);

        $activeClient = $this->createMock(Client::class);
        $activeClient->method('getId')->willReturn(1);
        $activeClient->method('isActive')->willReturn(true);

        $inactiveClient = $this->createMock(Client::class);
        $inactiveClient->method('getId')->willReturn(2);
        $inactiveClient->method('isActive')->willReturn(false);

        $clientRepo = $this->createMock(EntityRepository::class);
        $clientRepo->expects($this->once())
            ->method('findBy')
            ->with([], ['companyName' => 'ASC'])
            ->willReturn([$activeClient, $inactiveClient]);

        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $clientRepo],
            [Account::class, $this->createMock(EntityRepository::class)],
        ]);

        $response = $this->controller->list();
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data);
        $this->assertSame([1, 2], array_column($data, 'id'));
        $this->assertFalse($data[1]['isActive']);
    }

    public function testListForNonAdminReturnsOwnCompanyWhenActive(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(5);
        $client->method('isActive')->willReturn(true);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($client);

        $this->authenticateAs($user, false);
        $this->accountRepoReturningEmpty();

        $response = $this->controller->list();
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertSame(5, $data[0]['id']);
    }

    public function testListForNonAdminReturnsEmptyWhenOwnCompanyIsInactive(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('isActive')->willReturn(false);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($client);

        $this->authenticateAs($user, false);

        $response = $this->controller->list();
        $data = json_decode($response->getContent(), true);

        $this->assertSame([], $data);
    }

    // ---- COMR-64 (D): interruptor de mercado por cuenta ----

    private function accountOfClient(int $clientId, int $accountId): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);
        $account->method('getClient')->willReturn($client);

        return $account;
    }

    public function testSetMarketDelegatesToTheServiceWithTheAdminAsAuthor(): void
    {
        $admin = $this->createMock(User::class);
        $this->authenticateAs($admin, true);
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $account = $this->accountOfClient(1, 5);
        $account->method('isMarketEnabled')->willReturn(true);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $this->repoFinding($client)],
            [Account::class, $this->repoFinding($account)],
        ]);

        $this->accountSecurityService->expects($this->once())->method('setMarketEnabled')->with($account, true, $admin)->willReturn($account);
        $this->accountSecurityService->method('isMarketOpen')->willReturn(false);

        $data = json_decode($this->controller->setMarket(1, 5, new \App\DTO\SetAccountMarketDto(enabled: true))->getContent(), true);

        $this->assertSame(5, $data['id']);
        $this->assertTrue($data['marketEnabled']);
        $this->assertFalse($data['marketOpen'], 'Habilitada, pero el interruptor global sigue apagado.');
    }

    public function testSetMarketReturnsNotFoundForAnAccountOfAnotherClient(): void
    {
        $this->authenticateAs($this->createMock(User::class), true);
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $this->repoFinding($client)],
            [Account::class, $this->repoFinding($this->accountOfClient(2, 5))],
        ]);

        $this->accountSecurityService->expects($this->never())->method('setMarketEnabled');

        $this->assertSame(404, $this->controller->setMarket(1, 5, new \App\DTO\SetAccountMarketDto(enabled: true))->getStatusCode());
    }

    public function testTheAdminSeesTheMarketStateAndAuditOfEachAccountButOthersDoNot(): void
    {
        $account = $this->accountOfClient(1, 5);
        $account->method('isMarketEnabled')->willReturn(true);
        $account->method('getMarketChangedAt')->willReturn(new \DateTimeImmutable('2026-10-12T10:00:00+00:00'));
        $changer = $this->createMock(User::class);
        $changer->method('getEmail')->willReturn('admin@example.test');
        $account->method('getMarketChangedBy')->willReturn($changer);
        $this->accountSecurityService->method('isMarketOpen')->willReturn(true);
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $client->method('isActive')->willReturn(true);
        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);
        $this->em->method('getRepository')->willReturnMap([
            [Client::class, $this->repoFinding($client)],
            [Account::class, $accountRepo],
        ]);

        $this->authenticateAs($this->createMock(User::class), true);
        $admin = json_decode($this->controller->show(1)->getContent(), true)['accounts'][0];
        $this->assertTrue($admin['marketEnabled']);
        $this->assertTrue($admin['marketOpen']);
        $this->assertSame('admin@example.test', $admin['marketChangedBy']);
        $this->assertSame('2026-10-12T10:00:00+00:00', $admin['marketChangedAt']);
    }

    private function repoFinding(object $entity): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($entity);

        return $repo;
    }

    // ---- COMR-272: la ficha del cliente muestra su unidad organizativa ----

    public function testListExposesTheOrgUnitOfTheClient(): void
    {
        $unit = $this->createMock(\App\Market\Organizacion\Domain\OrgUnit::class);
        $unit->method('id')->willReturn(7);
        $unit->method('name')->willReturn('AWS Malta Ltd');
        $unit->method('countryCode')->willReturn('MT');
        $unit->method('taxId')->willReturn('C63841');
        $unit->method('types')->willReturn([\App\Market\Organizacion\Domain\OrgUnitType::BUYER]);

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(2);
        $client->method('getOrgUnit')->willReturn($unit);
        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($client);
        $client->method('isActive')->willReturn(true);
        $this->authenticateAs($user, false);
        $this->accountRepoReturningEmpty();

        $data = json_decode($this->controller->list()->getContent(), true);

        $this->assertSame(
            ['id' => 7, 'name' => 'AWS Malta Ltd', 'countryCode' => 'MT', 'countryCodeIso3' => 'MLT', 'taxId' => 'C63841', 'types' => ['buyer']],
            $data[0]['orgUnit'],
        );
    }

    public function testListReportsNoOrgUnitForAClientNotYetLinked(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(2);
        $client->method('getOrgUnit')->willReturn(null);
        $client->method('isActive')->willReturn(true);
        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($client);
        $this->authenticateAs($user, false);
        $this->accountRepoReturningEmpty();

        $data = json_decode($this->controller->list()->getContent(), true);

        $this->assertArrayHasKey('orgUnit', $data[0]);
        $this->assertNull($data[0]['orgUnit']);
    }
}
