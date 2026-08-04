<?php

namespace App\Tests\Command\StagingSync;

use App\Command\StagingSync\ReportStagingSyncCommand;
use App\Enums\StagingSyncStatusEnum;
use App\Service\StagingSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \App\Command\StagingSync\ReportStagingSyncCommand
 *
 * Primer comando del repo probado con CommandTester — no había precedente,
 * sigue el patrón estándar de Symfony (sin nada específico de este repo que
 * replicar).
 */
class ReportStagingSyncCommandTest extends TestCase
{
    private function tester(StagingSyncService $service): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ReportStagingSyncCommand($service));

        return new CommandTester($application->find('app:staging-sync:report'));
    }

    public function testDelegatesToTheServiceWithParsedArguments(): void
    {
        $service = $this->createMock(StagingSyncService::class);
        $service->expects($this->once())
            ->method('report')
            ->with(StagingSyncStatusEnum::FAILED, 'cron', 'el rsync explotó');

        $exitCode = $this->tester($service)->execute([
            'status' => 'FAILED',
            '--triggered-by' => 'cron',
            '--error' => 'el rsync explotó',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testRejectsAnUnknownStatus(): void
    {
        $service = $this->createMock(StagingSyncService::class);
        $service->expects($this->never())->method('report');

        $exitCode = $this->tester($service)->execute(['status' => 'BOGUS']);

        $this->assertSame(Command::INVALID, $exitCode);
    }
}
