<?php

namespace App\Tests\Logging;

use App\Logging\AgeLimitedRotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Logging\AgeLimitedRotatingFileHandler
 */
class AgeLimitedRotatingFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/age-limited-handler-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testFirstWriteOfTheDayDeletesFilesOlderThanTheLimit(): void
    {
        $this->seed(['-30 days', '-20 days', '-16 days', '-15 days', '-14 days', '-1 day']);

        $this->handler(15)->handle($this->record());

        $this->assertSame(
            [$this->day('-15 days'), $this->day('-14 days'), $this->day('-1 day'), $this->day('today')],
            $this->days(),
            'Se conservan los últimos 15 días (más hoy); lo anterior se borra por antigüedad.',
        );
    }

    public function testTodaysRecordIsWrittenAsOneJsonLine(): void
    {
        $this->handler(15)->handle($this->record('hola'));

        $file = $this->dir . '/request.test.' . $this->day('today') . '.log';
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lines);
        $this->assertSame('hola', json_decode($lines[0], true)['message']);
    }

    public function testFewFilesFarApartAreStillPurgedByAge(): void
    {
        // Pocos ficheros (menos que cualquier tope por número) pero muy antiguos:
        // el criterio es la edad, no la cantidad.
        $this->seed(['-200 days', '-100 days']);

        $this->handler(15)->handle($this->record());

        $this->assertSame([$this->day('today')], $this->days());
    }

    public function testUnrelatedFilesInTheDirectoryAreLeftAlone(): void
    {
        touch($this->dir . '/otro.log');
        touch($this->dir . '/request.test.no-es-fecha.log');
        $this->seed(['-40 days']);

        $this->handler(15)->handle($this->record());

        $this->assertFileExists($this->dir . '/otro.log');
        $this->assertFileExists($this->dir . '/request.test.no-es-fecha.log');
        $this->assertFileDoesNotExist($this->dir . '/request.test.' . $this->day('-40 days') . '.log');
    }

    private function handler(int $maxAgeDays): AgeLimitedRotatingFileHandler
    {
        $handler = new AgeLimitedRotatingFileHandler($this->dir . '/request.test.log', $maxAgeDays, Level::Info);
        $handler->setFilenameFormat('{filename}.{date}', 'Y-m-d');
        $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());

        return $handler;
    }

    private function record(string $message = 'Client request'): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'client_request', Level::Info, $message);
    }

    /** @param list<string> $relativeDays */
    private function seed(array $relativeDays): void
    {
        foreach ($relativeDays as $relative) {
            file_put_contents($this->dir . '/request.test.' . $this->day($relative) . '.log', "{}\n");
        }
    }

    private function day(string $relative): string
    {
        return (new \DateTimeImmutable($relative))->format('Y-m-d');
    }

    /** @return list<string> fechas de los request.test.<fecha>.log presentes, ordenadas */
    private function days(): array
    {
        $days = [];
        foreach (glob($this->dir . '/request.test.*.log') ?: [] as $file) {
            if (preg_match('/\.(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                $days[] = $m[1];
            }
        }
        sort($days);

        return $days;
    }
}
