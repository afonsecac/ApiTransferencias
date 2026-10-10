<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ReportMarked;
use App\Entity\User;
use App\Service\ReportService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Cubre el bug real de 2026-10-10: ReportService::getAllReports() llamaba a
 * ReportMarkedRepository::list() con el orden de argumentos equivocado
 * (page/limit cruzados) y el repositorio nunca aplicaba setFirstResult()/
 * setMaxResults(), así que la paginación era puramente cosmética (siempre
 * devolvía el set completo). También cubre el filtro por cliente para
 * ROLE_ADMIN, que antes era todo-o-nada.
 *
 * @covers \App\Service\ReportService
 * @covers \App\Repository\ReportMarkedRepository
 */
class ReportServiceFunctionalTest extends ProviderFunctionalTestCase
{
    private function reportUser(Client $client, array $roles): User
    {
        static $counter = 0;
        $counter++;

        $user = (new User())
            ->setEmail("report-func-{$counter}@example.test")
            ->setPassword('irrelevant-hash')
            ->setFirstName('Report')
            ->setLastName("Funcional {$counter}")
            ->setRoles($roles)
            ->setIsActive(true)
            ->setIsCheckValidation(true)
            ->setCompany($client);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function authenticateReportUser(User $user): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function markedReport(Client $client, Account $account, string $name): ReportMarked
    {
        $report = (new ReportMarked())
            ->setName($name)
            ->setCreatedAt(new \DateTimeImmutable('now'))
            ->setLastOperationMarked(1)
            ->setClient($client)
            ->setAccount($account);

        $this->em->persist($report);

        return $report;
    }

    public function testPaginationActuallyLimitsTheResultSet(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        for ($i = 1; $i <= 5; $i++) {
            $this->markedReport($client, $account, "Reporte {$i}");
        }
        $this->em->flush();

        $user = $this->reportUser($client, ['ROLE_ADMIN', 'ROLE_SYSTEM_SHOW']);
        $this->authenticateReportUser($user);

        $service = self::getContainer()->get(ReportService::class);
        $result = $service->getAllReports(null, 0, 2);

        $this->assertSame(5, $result->getTotal());
        $this->assertCount(2, $result->getResults());

        $secondPage = $service->getAllReports(null, 1, 2);
        $this->assertCount(2, $secondPage->getResults());

        $thirdPage = $service->getAllReports(null, 2, 2);
        $this->assertCount(1, $thirdPage->getResults());
    }

    public function testAdminCanFilterByAnArbitraryClient(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();
        $environment = $this->createEnvironment();
        $accountA = $this->createAccount($clientA, $environment);
        $accountB = $this->createAccount($clientB, $environment);

        $this->markedReport($clientA, $accountA, 'Reporte A');
        $this->markedReport($clientB, $accountB, 'Reporte B1');
        $this->markedReport($clientB, $accountB, 'Reporte B2');
        $this->em->flush();

        $admin = $this->reportUser($clientA, ['ROLE_ADMIN', 'ROLE_SYSTEM_SHOW']);
        $this->authenticateReportUser($admin);

        $service = self::getContainer()->get(ReportService::class);
        $result = $service->getAllReports($clientB->getId(), 0, 40);

        $this->assertSame(2, $result->getTotal());
    }

    public function testNonAdminAlwaysSeesOnlyTheirOwnClientRegardlessOfRequestedClientId(): void
    {
        $ownClient = $this->createClient();
        $otherClient = $this->createClient();
        $environment = $this->createEnvironment();
        $ownAccount = $this->createAccount($ownClient, $environment);
        $otherAccount = $this->createAccount($otherClient, $environment);

        $this->markedReport($ownClient, $ownAccount, 'Reporte propio');
        $this->markedReport($otherClient, $otherAccount, 'Reporte ajeno');
        $this->em->flush();

        $user = $this->reportUser($ownClient, ['ROLE_SYSTEM_SHOW']);
        $this->authenticateReportUser($user);

        $service = self::getContainer()->get(ReportService::class);
        $result = $service->getAllReports(null, 0, 40);

        $this->assertSame(1, $result->getTotal());
        $this->assertSame('Reporte propio', $result->getResults()[0]->getName());
    }

    public function testNonAdminRequestingAnotherClientIdIsDenied(): void
    {
        $ownClient = $this->createClient();
        $otherClient = $this->createClient();
        $user = $this->reportUser($ownClient, ['ROLE_SYSTEM_SHOW']);
        $this->authenticateReportUser($user);

        $service = self::getContainer()->get(ReportService::class);

        $this->expectException(AccessDeniedException::class);
        $service->getAllReports($otherClient->getId(), 0, 40);
    }
}
