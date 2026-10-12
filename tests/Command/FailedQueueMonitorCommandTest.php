<?php

namespace App\Tests\Command;

use App\Command\FailedQueueMonitorCommand;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * COMR-262: aviso cuando la cola `failed` de Messenger acumula mensajes o tiene alguno viejo, para que
 * un fallo no pase meses sin que nadie lo vea (40 mensajes sin atender desde el 15/05/2026).
 *
 * @covers \App\Command\FailedQueueMonitorCommand
 */
class FailedQueueMonitorCommandTest extends FunctionalTestCase
{
    private Connection $connection;
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->em->getConnection();
        // Dentro de la transacción del test (se revierte): la cola arranca vacía.
        $this->connection->executeStatement("DELETE FROM messenger_messages WHERE queue_name = 'failed'");

        $this->tester = new CommandTester(new FailedQueueMonitorCommand($this->connection));
    }

    private function failed(int $count, string $age = '1 minute', string $type = 'App\\\\Message\\\\CheckSaleMessage'): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->connection->executeStatement(
                "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES ('{}', :headers, 'failed', NOW() - CAST(:age AS interval), NOW())",
                ['headers' => '{"type":"' . $type . '"}', 'age' => $age],
            );
        }
    }

    public function testEmptyQueueIsSilentAndSucceeds(): void
    {
        $exit = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertSame('', trim($this->tester->getDisplay()));
    }

    public function testAFewRecentMessagesAreTolerated(): void
    {
        $this->failed(3);

        $this->assertSame(Command::SUCCESS, $this->tester->execute([]));
    }

    public function testTooManyMessagesAreReportedAsAProblem(): void
    {
        $this->failed(11);

        $exit = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('11 mensajes', $this->tester->getDisplay());
        $this->assertStringContainsString('messenger:failed:show', $this->tester->getDisplay());
    }

    public function testAnOldMessageIsReportedEvenIfThereAreFew(): void
    {
        $this->failed(1, '30 hours');

        $exit = $this->tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('más de 24 h', $this->tester->getDisplay());
    }

    public function testThresholdsAreConfigurable(): void
    {
        $this->failed(3, '2 hours');

        $exit = $this->tester->execute(['--max-messages' => '2', '--max-age-hours' => '48']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('3 mensajes', $this->tester->getDisplay());
    }

    public function testReportBreaksTheQueueDownByMessageType(): void
    {
        $this->failed(8, '1 minute', 'App\\\\Message\\\\CheckSaleMessage');
        $this->failed(4, '1 minute', 'App\\\\Message\\\\DispatchPendingEmailMessage');

        $this->tester->execute([]);

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('CheckSaleMessage: 8', $display);
        $this->assertStringContainsString('DispatchPendingEmailMessage: 4', $display);
    }

    public function testNeverPrintsMessageBodies(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES ('SECRETO-DEL-CUERPO', '{\"type\":\"App\\\\\\\\Message\\\\\\\\X\"}', 'failed', NOW() - interval '3 days', NOW())",
        );

        $this->tester->execute([]);

        $this->assertStringNotContainsString('SECRETO-DEL-CUERPO', $this->tester->getDisplay());
    }
}
