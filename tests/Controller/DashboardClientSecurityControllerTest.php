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
}
