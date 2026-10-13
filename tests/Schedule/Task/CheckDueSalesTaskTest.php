<?php

namespace App\Tests\Schedule\Task;

use App\Schedule\Task\CheckDueSalesTask;
use App\Service\CommunicationSaleService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * @covers \App\Schedule\Task\CheckDueSalesTask
 */
class CheckDueSalesTaskTest extends TestCase
{
    public function testItAsksTheServiceToDispatchTheDueChecksWithTheConfiguredLimits(): void
    {
        $service = $this->createMock(CommunicationSaleService::class);
        $service->expects($this->once())->method('dispatchDueStatusChecks')
            ->with(CheckDueSalesTask::PER_PROVIDER_LIMIT, CheckDueSalesTask::LEASE_SECONDS)
            ->willReturn(3);

        (new CheckDueSalesTask($service, new NullLogger()))();
    }

    public function testADatabaseFailureDoesNotBreakTheScheduler(): void
    {
        $service = $this->createMock(CommunicationSaleService::class);
        $service->method('dispatchDueStatusChecks')->willThrowException(new \RuntimeException('db down'));

        (new CheckDueSalesTask($service, new NullLogger()))();

        $this->addToAssertionCount(1);
    }

    public function testRunsEveryFifteenSecondsSoTheFirstBackoffStepsAreRespected(): void
    {
        $attributes = (new \ReflectionClass(CheckDueSalesTask::class))->getAttributes(AsPeriodicTask::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('15 seconds', $attributes[0]->newInstance()->frequency);
    }

    public function testTheLeaseOutlastsTheCheckItself(): void
    {
        // El arrendamiento tiene que durar más que una consulta lenta al proveedor (timeout de 5 s del cliente,
        // varias consultas por mensaje no hay) para que no se solape con la siguiente pasada.
        $this->assertGreaterThanOrEqual(60, CheckDueSalesTask::LEASE_SECONDS);
    }
}
