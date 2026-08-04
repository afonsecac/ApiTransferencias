<?php

namespace App\Tests\Service;

use App\Entity\StagingSyncRun;
use App\Entity\User;
use App\Enums\StagingSyncStatusEnum;
use App\Exception\MyCurrentException;
use App\Repository\StagingSyncRunRepository;
use App\Service\StagingSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\StagingSyncService
 */
class StagingSyncServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private StagingSyncRunRepository&MockObject $repository;
    private string $triggerDir;
    private StagingSyncService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(StagingSyncRunRepository::class);
        $this->triggerDir = sys_get_temp_dir() . '/staging-sync-test-' . uniqid();

        $this->service = new StagingSyncService($this->em, $this->repository, $this->triggerDir);
    }

    protected function tearDown(): void
    {
        $file = $this->triggerDir . '/request.json';
        if (is_file($file)) {
            unlink($file);
        }
        if (is_dir($this->triggerDir)) {
            rmdir($this->triggerDir);
        }
    }

    private function user(string $email): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    // ---- trigger ----

    public function testTriggerWritesTheFlagFileWithTheTriggeringUser(): void
    {
        $this->repository->method('findLatest')->willReturn(null);

        $this->service->trigger($this->user('admin@example.test'));

        $file = $this->triggerDir . '/request.json';
        $this->assertFileExists($file);
        $data = json_decode(file_get_contents($file), true);
        $this->assertSame('admin@example.test', $data['triggeredBy']);
    }

    public function testTriggerRejectsWhenAFlagFileIsAlreadyPending(): void
    {
        $this->repository->method('findLatest')->willReturn(null);
        $this->service->trigger($this->user('first@example.test'));

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(409);

        $this->service->trigger($this->user('second@example.test'));
    }

    public function testTriggerRejectsWhenALatestRunIsStillRunning(): void
    {
        $running = (new StagingSyncRun())->setStatus(StagingSyncStatusEnum::RUNNING)->setStartedAt(new \DateTimeImmutable());
        $this->repository->method('findLatest')->willReturn($running);

        try {
            $this->service->trigger($this->user('admin@example.test'));
            $this->fail('Se esperaba MyCurrentException por sincronización en curso.');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->assertFileDoesNotExist($this->triggerDir . '/request.json');
    }

    public function testTriggerAllowsANewRequestWhenTheLatestRunAlreadyFinished(): void
    {
        $finished = (new StagingSyncRun())
            ->setStatus(StagingSyncStatusEnum::SUCCESS)
            ->setStartedAt(new \DateTimeImmutable())
            ->setFinishedAt(new \DateTimeImmutable());
        $this->repository->method('findLatest')->willReturn($finished);

        $this->service->trigger($this->user('admin@example.test'));

        $this->assertFileExists($this->triggerDir . '/request.json');
    }

    // ---- report ----

    public function testReportRunningAlwaysCreatesANewRow(): void
    {
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(StagingSyncRun::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->report(StagingSyncStatusEnum::RUNNING, 'cron', null);
    }

    public function testReportSuccessClosesTheLatestRunningRow(): void
    {
        $running = (new StagingSyncRun())->setStatus(StagingSyncStatusEnum::RUNNING)->setStartedAt(new \DateTimeImmutable());
        $this->repository->method('findOneBy')
            ->with(['status' => StagingSyncStatusEnum::RUNNING], ['id' => 'DESC'])
            ->willReturn($running);
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->report(StagingSyncStatusEnum::SUCCESS, 'cron', null);

        $this->assertSame(StagingSyncStatusEnum::SUCCESS, $running->getStatus());
        $this->assertNotNull($running->getFinishedAt());
        $this->assertNull($running->getErrorMessage());
    }

    public function testReportFailedStoresTheErrorMessage(): void
    {
        $running = (new StagingSyncRun())->setStatus(StagingSyncStatusEnum::RUNNING)->setStartedAt(new \DateTimeImmutable());
        $this->repository->method('findOneBy')->willReturn($running);

        $this->service->report(StagingSyncStatusEnum::FAILED, 'cron', 'el rsync explotó');

        $this->assertSame(StagingSyncStatusEnum::FAILED, $running->getStatus());
        $this->assertSame('el rsync explotó', $running->getErrorMessage());
    }

    public function testReportFinalStatusWithoutARunningRowCreatesOneToAvoidLosingTheData(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(StagingSyncRun::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->report(StagingSyncStatusEnum::FAILED, 'cron', 'nunca se vio el RUNNING');
    }

    // ---- latest/recent ----

    public function testLatestDelegatesToTheRepository(): void
    {
        $run = (new StagingSyncRun())->setStatus(StagingSyncStatusEnum::SUCCESS)->setStartedAt(new \DateTimeImmutable());
        $this->repository->method('findLatest')->willReturn($run);

        $this->assertSame($run, $this->service->latest());
    }

    public function testRecentDelegatesToTheRepository(): void
    {
        $runs = [(new StagingSyncRun())->setStatus(StagingSyncStatusEnum::SUCCESS)->setStartedAt(new \DateTimeImmutable())];
        $this->repository->method('findRecent')->willReturn($runs);

        $this->assertSame($runs, $this->service->recent());
    }
}
