<?php

namespace App\Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * scripts/health-check.sh decide si un despliegue quedó sano. La comprobación
 * anterior (`curl -sf http://localhost/health/ready`) daba por bueno un 301 de
 * Traefik: `-f` solo falla con códigos >= 400. Aquí se ejecuta el script real
 * contra un servidor simulado.
 *
 * @coversNothing
 */
class HealthCheckScriptTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @var resource|null */
    private $server = null;
    private int $port;
    private string $stateFile;

    protected function setUp(): void
    {
        $this->stateFile = sys_get_temp_dir() . '/health-flaky-' . bin2hex(random_bytes(6));
        $this->port = $this->freePort();
        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/health_router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['FLAKY_STATE_FILE' => $this->stateFile, 'FLAKY_FAILURES' => '2', 'PATH' => getenv('PATH')],
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $this->port); ++$i) {
            usleep(100_000);
        }
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        @unlink($this->stateFile);
    }

    public function testHealthyAppPasses(): void
    {
        [$exit, $out] = $this->check('/ok');

        $this->assertSame(0, $exit, $out);
        $this->assertStringContainsString('Health check OK', $out);
    }

    public function testARedirectIsNotHealthy(): void
    {
        // El caso que la comprobación anterior daba por bueno.
        [$exit, $out] = $this->check('/redirect');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HTTP 301', $out);
    }

    public function testServiceUnavailableFails(): void
    {
        [$exit, $out] = $this->check('/down', retries: '0');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HTTP 503', $out);
    }

    public function testOkStatusCodeWithAnUnhealthyBodyFails(): void
    {
        [$exit, $out] = $this->check('/wrong-body');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('no indica estado ok', $out);
    }

    public function testEmptyBodyFails(): void
    {
        [$exit] = $this->check('/empty');

        $this->assertSame(1, $exit);
    }

    public function testNotFoundFails(): void
    {
        [$exit, $out] = $this->check('/nope');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HTTP 404', $out);
    }

    public function testRetriesWhileTheAppIsStartingUp(): void
    {
        // Tras recrear contenedores, php-fpm tarda unos segundos: 503, 503 y luego 200.
        [$exit, $out] = $this->check('/flaky', retries: '3');

        $this->assertSame(0, $exit, $out);
        $this->assertSame('3', file_get_contents($this->stateFile), 'Debe haber hecho 3 peticiones.');
    }

    public function testGivesUpWhenTheAppNeverRecovers(): void
    {
        [$exit, $out] = $this->check('/flaky', retries: '1');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HTTP 503', $out);
    }

    public function testConnectionRefusedFails(): void
    {
        [$exit, $out] = $this->check('/ok', port: $this->freePort(), retries: '0');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HTTP 000', $out);
    }

    public function testMissingUrlIsAUsageError(): void
    {
        [$exit] = $this->exec([]);

        $this->assertSame(2, $exit);
    }

    public function testScriptIsExecutable(): void
    {
        $this->assertFileExists(self::ROOT . '/scripts/health-check.sh');
        $this->assertTrue(is_executable(self::ROOT . '/scripts/health-check.sh'));
    }

    public function testProductionDeployUsesTheScriptOverHttpsWithTheRealDomain(): void
    {
        $workflow = (string) file_get_contents(self::ROOT . '/.github/workflows/deploy-prod.yaml');

        $this->assertStringContainsString('./scripts/health-check.sh "https://${DOMAIN}/health/ready" "${DOMAIN}:443:127.0.0.1"', $workflow);
        $this->assertStringNotContainsString('curl -sf http://localhost/health/ready', $workflow);
    }

    /** @return array{int, string} */
    private function check(string $path, ?int $port = null, string $retries = '2'): array
    {
        return $this->exec(['http://127.0.0.1:' . ($port ?? $this->port) . $path], $retries);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string} código de salida y stdout+stderr
     */
    private function exec(array $args, string $retries = '2'): array
    {
        $process = proc_open(
            array_merge(['sh', self::ROOT . '/scripts/health-check.sh'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => getenv('PATH'), 'HEALTH_RETRIES' => $retries, 'HEALTH_RETRY_DELAY' => '1'],
        );
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        return [proc_close($process), $out];
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
        fclose($socket);

        return $port;
    }
}
