<?php

namespace App\Tests\Controller;

use App\Controller\DashboardInformationController;
use App\Service\DashboardStatisticsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\DashboardInformationController
 */
class DashboardInformationControllerTest extends TestCase
{
    private DashboardStatisticsService&MockObject $statsService;
    private DashboardInformationController $controller;

    protected function setUp(): void
    {
        $this->statsService = $this->createMock(DashboardStatisticsService::class);
        $this->controller = new DashboardInformationController($this->statsService);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    // ── Summary ─────────────────────────────────────────────────

    public function testSummaryReturnsJsonWithServiceData(): void
    {
        $expected = [
            'totalOperations' => 100,
            'completed' => 80,
            'failed' => 10,
            'pending' => 5,
            'rejected' => 3,
            'created' => 1,
            'reserved' => 1,
            'successRate' => 80.0,
            'totalAmount' => 5000.0,
            'avgAmount' => 50.0,
        ];

        $this->statsService->expects($this->once())
            ->method('getSummary')
            ->with([
                'dateFrom' => '2026-01-01',
                'dateTo' => '2026-03-31',
                'clientId' => 5,
                'environmentType' => 'TEST',
                'type' => 'recharge',
            ])
            ->willReturn($expected);

        $request = new Request([
            'dateFrom' => '2026-01-01',
            'dateTo' => '2026-03-31',
            'clientId' => '5',
            'environmentType' => 'TEST',
            'type' => 'recharge',
        ]);

        $response = $this->controller->summary($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['totalOperations']);
        $this->assertSame(80, $data['completed']);
        $this->assertEquals(80.0, $data['successRate']);
    }

    public function testSummaryWithNoFilters(): void
    {
        $this->statsService->expects($this->once())
            ->method('getSummary')
            ->with([
                'dateFrom' => null,
                'dateTo' => null,
                'clientId' => null,
                'environmentType' => null,
                'type' => null,
            ])
            ->willReturn(['totalOperations' => 0]);

        $response = $this->controller->summary(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    // ── Operations by client ────────────────────────────────────

    public function testOperationsByClientReturnsArray(): void
    {
        $expected = [
            ['clientId' => 1, 'clientName' => 'Client A', 'total' => 50],
            ['clientId' => 2, 'clientName' => 'Client B', 'total' => 30],
        ];

        $this->statsService->expects($this->once())
            ->method('getOperationsByClient')
            ->willReturn($expected);

        $response = $this->controller->operationsByClient(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('Client A', $data[0]['clientName']);
    }

    // ── Operations over time ────────────────────────────────────

    public function testOperationsOverTimePassesGroupBy(): void
    {
        $expected = ['groupBy' => 'week', 'series' => [['period' => '2026-01-06', 'total' => 12]]];

        $this->statsService->expects($this->once())
            ->method('getOperationsOverTime')
            ->with($this->anything(), 'week')
            ->willReturn($expected);

        $request = new Request(['groupBy' => 'week']);
        $response = $this->controller->operationsOverTime($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('week', $data['groupBy']);
        $this->assertCount(1, $data['series']);
    }

    public function testOperationsOverTimeDefaultsGroupByToDay(): void
    {
        $this->statsService->expects($this->once())
            ->method('getOperationsOverTime')
            ->with($this->anything(), 'day')
            ->willReturn(['groupBy' => 'day', 'series' => []]);

        $response = $this->controller->operationsOverTime(new Request());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('day', $data['groupBy']);
    }

    // ── Busiest days ────────────────────────────────────────────

    public function testBusiestDaysReturnsArray(): void
    {
        $expected = [
            ['dayOfWeek' => 1, 'dayName' => 'Monday', 'total' => 120],
            ['dayOfWeek' => 5, 'dayName' => 'Friday', 'total' => 100],
        ];

        $this->statsService->expects($this->once())
            ->method('getBusiestDays')
            ->willReturn($expected);

        $response = $this->controller->busiestDays(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('Monday', $data[0]['dayName']);
    }

    // ── Peak hours ──────────────────────────────────────────────

    public function testPeakHoursReturnsArray(): void
    {
        $expected = [
            ['hour' => 10, 'total' => 95],
            ['hour' => 14, 'total' => 88],
        ];

        $this->statsService->expects($this->once())
            ->method('getPeakHours')
            ->willReturn($expected);

        $response = $this->controller->peakHours(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame(10, $data[0]['hour']);
    }

    // ── Top packages ────────────────────────────────────────────

    public function testTopPackagesPassesLimit(): void
    {
        $expected = [
            ['packageId' => 1, 'packageName' => 'Pack A', 'total' => 200, 'totalAmount' => 6000.0],
        ];

        $this->statsService->expects($this->once())
            ->method('getTopPackages')
            ->with($this->anything(), 25)
            ->willReturn($expected);

        $request = new Request(['limit' => '25']);
        $response = $this->controller->topPackages($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Pack A', $data[0]['packageName']);
    }

    public function testTopPackagesDefaultsLimitTo10(): void
    {
        $this->statsService->expects($this->once())
            ->method('getTopPackages')
            ->with($this->anything(), 10)
            ->willReturn([]);

        $this->controller->topPackages(new Request());
    }

    public function testTopPackagesClampsLimitTo50(): void
    {
        $this->statsService->expects($this->once())
            ->method('getTopPackages')
            ->with($this->anything(), 50)
            ->willReturn([]);

        $this->controller->topPackages(new Request(['limit' => '999']));
    }

    public function testTopPackagesClampsLimitMin1(): void
    {
        $this->statsService->expects($this->once())
            ->method('getTopPackages')
            ->with($this->anything(), 1)
            ->willReturn([]);

        $this->controller->topPackages(new Request(['limit' => '-5']));
    }

    // ── Filter extraction ───────────────────────────────────────

    public function testClientIdParsedAsInteger(): void
    {
        $this->statsService->expects($this->once())
            ->method('getSummary')
            ->with($this->callback(function (array $filters) {
                return $filters['clientId'] === 42;
            }))
            ->willReturn(['totalOperations' => 0]);

        $this->controller->summary(new Request(['clientId' => '42']));
    }

    public function testClientIdNullWhenNotProvided(): void
    {
        $this->statsService->expects($this->once())
            ->method('getSummary')
            ->with($this->callback(function (array $filters) {
                return $filters['clientId'] === null;
            }))
            ->willReturn(['totalOperations' => 0]);

        $this->controller->summary(new Request());
    }
}
