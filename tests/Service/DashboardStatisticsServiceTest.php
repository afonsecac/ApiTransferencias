<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\DashboardStatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\DashboardStatisticsService
 */
class DashboardStatisticsServiceTest extends TestCase
{
    private Security&MockObject $security;
    private CommunicationSaleInfoRepository&MockObject $saleRepo;
    private DashboardStatisticsService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->saleRepo = $this->createMock(CommunicationSaleInfoRepository::class);

        $this->service = new DashboardStatisticsService(
            $em,
            $this->security,
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $this->createMock(SysConfigRepository::class),
            $this->createMock(SerializerInterface::class),
            $this->saleRepo,
        );
    }

    private function mockUser(?int $companyId = 1): User&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($companyId);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($client);

        $this->security->method('getUser')->willReturn($user);

        return $user;
    }

    // ── Access control ──────────────────────────────────────────

    public function testAdminCanQueryAllClients(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(null, $this->isInstanceOf(\DateTimeImmutable::class), $this->isInstanceOf(\DateTimeImmutable::class), null, null)
            ->willReturn(['totalOperations' => 10]);

        $result = $this->service->getSummary([]);

        $this->assertSame(10, $result['totalOperations']);
    }

    public function testAdminCanQuerySpecificClient(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(5, $this->anything(), $this->anything(), null, null)
            ->willReturn(['totalOperations' => 3]);

        $result = $this->service->getSummary(['clientId' => 5]);

        $this->assertSame(3, $result['totalOperations']);
    }

    public function testSystemAdminForcedToOwnClient(): void
    {
        $this->mockUser(7);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(7, $this->anything(), $this->anything(), null, null)
            ->willReturn(['totalOperations' => 5]);

        $result = $this->service->getSummary([]);

        $this->assertSame(5, $result['totalOperations']);
    }

    public function testSystemAdminCanQueryOwnClient(): void
    {
        $this->mockUser(7);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(7, $this->anything(), $this->anything(), null, null)
            ->willReturn(['totalOperations' => 5]);

        $result = $this->service->getSummary(['clientId' => 7]);

        $this->assertSame(5, $result['totalOperations']);
    }

    public function testSystemAdminCannotQueryOtherClient(): void
    {
        $this->mockUser(7);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->service->getSummary(['clientId' => 999]);
    }

    public function testNonUserThrowsAccessDenied(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->security->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->service->getSummary([]);
    }

    // ── Date defaults ───────────────────────────────────────────

    public function testDefaultDateRangeIsLastYear(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(
                null,
                $this->callback(function (\DateTimeImmutable $dateFrom) {
                    $expected = new \DateTimeImmutable('-1 year');
                    return abs($dateFrom->getTimestamp() - $expected->getTimestamp()) < 5;
                }),
                $this->callback(function (\DateTimeImmutable $dateTo) {
                    $expected = (new \DateTimeImmutable('now'))->modify('+1 day');
                    return abs($dateTo->getTimestamp() - $expected->getTimestamp()) < 5;
                }),
                null,
                null,
            )
            ->willReturn(['totalOperations' => 0]);

        $this->service->getSummary([]);
    }

    public function testCustomDateRangeIsParsed(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(
                null,
                $this->callback(fn(\DateTimeImmutable $d) => $d->format('Y-m-d') === '2026-01-01'),
                $this->callback(fn(\DateTimeImmutable $d) => $d->format('Y-m-d') === '2026-04-01'),
                null,
                null,
            )
            ->willReturn(['totalOperations' => 0]);

        $this->service->getSummary(['dateFrom' => '2026-01-01', 'dateTo' => '2026-03-31']);
    }

    // ── Filter passthrough ──────────────────────────────────────

    public function testFiltersPassedToRepository(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsSummary')
            ->with(3, $this->anything(), $this->anything(), 'TEST', 'recharge')
            ->willReturn(['totalOperations' => 0]);

        $this->service->getSummary([
            'clientId' => 3,
            'environmentType' => 'TEST',
            'type' => 'recharge',
        ]);
    }

    // ── Delegation to each repo method ──────────────────────────

    public function testGetOperationsByClientDelegatesToRepo(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $expected = [['clientId' => 1, 'clientName' => 'Test', 'total' => 10]];
        $this->saleRepo->expects($this->once())
            ->method('getStatsOperationsByClient')
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->getOperationsByClient([]));
    }

    public function testGetOperationsOverTimeDelegatesToRepo(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $expected = ['groupBy' => 'month', 'series' => []];
        $this->saleRepo->expects($this->once())
            ->method('getStatsOperationsOverTime')
            ->with(null, $this->anything(), $this->anything(), null, null, 'month')
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->getOperationsOverTime([], 'month'));
    }

    public function testGetOperationsOverTimeDefaultsInvalidGroupByToDay(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->saleRepo->expects($this->once())
            ->method('getStatsOperationsOverTime')
            ->with(null, $this->anything(), $this->anything(), null, null, 'day')
            ->willReturn(['groupBy' => 'day', 'series' => []]);

        $this->service->getOperationsOverTime([], 'invalid');
    }

    public function testGetBusiestDaysDelegatesToRepo(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $expected = [['dayOfWeek' => 1, 'dayName' => 'Monday', 'total' => 50]];
        $this->saleRepo->expects($this->once())
            ->method('getStatsBusiestDays')
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->getBusiestDays([]));
    }

    public function testGetPeakHoursDelegatesToRepo(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $expected = [['hour' => 14, 'total' => 95]];
        $this->saleRepo->expects($this->once())
            ->method('getStatsPeakHours')
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->getPeakHours([]));
    }

    public function testGetTopPackagesDelegatesToRepo(): void
    {
        $this->mockUser(1);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $expected = [['packageId' => 1, 'packageName' => 'Pack', 'total' => 30, 'totalAmount' => 900.0]];
        $this->saleRepo->expects($this->once())
            ->method('getStatsTopPackages')
            ->with(null, $this->anything(), $this->anything(), null, null, 15)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->getTopPackages([], 15));
    }
}
