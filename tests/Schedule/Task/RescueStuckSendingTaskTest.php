<?php

namespace App\Tests\Schedule\Task;

use App\Schedule\Task\RescueStuckSendingTask;
use App\Service\CommunicationSaleService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Schedule\Task\RescueStuckSendingTask
 */
class RescueStuckSendingTaskTest extends TestCase
{
    public function testItAsksTheServiceToRescueSalesStuckForMoreThanTheConfiguredMinutes(): void
    {
        $service = $this->createMock(CommunicationSaleService::class);
        $service->expects($this->once())->method('rescueStuckSendingSales')->with(RescueStuckSendingTask::OLDER_THAN_MINUTES)->willReturn(2);

        (new RescueStuckSendingTask($service, new NullLogger()))();
    }

    public function testADatabaseFailureDoesNotBreakTheScheduler(): void
    {
        $service = $this->createMock(CommunicationSaleService::class);
        $service->method('rescueStuckSendingSales')->willThrowException(new \RuntimeException('db down'));

        (new RescueStuckSendingTask($service, new NullLogger()))();

        $this->addToAssertionCount(1);
    }
}
