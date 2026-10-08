<?php

namespace App\Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * monitor.sh (cron cada 5 minutos en el VPS de producción). Se ejecuta el script
 * real con sustitutos de docker, df, free y del health check, y se comprueba qué
 * avisa, cuándo y que no repite el aviso en cada ciclo.
 *
 * @coversNothing
 */
class MonitorScriptTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const MAILTO = 'ops@example.test';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/monitor-test-' . bin2hex(random_bytes(6));
        foreach (['app/scripts', 'bin', 'state', 'logs'] as $sub) {
            mkdir($this->dir . '/' . $sub, 0777, true);
        }
        file_put_contents($this->dir . '/app/.env.vps', "POSTGRES_USER=x\nDOMAIN=api.example.test\n");
        $this->shim('docker', "echo \"\$*\" | tr '\\n' ' ' >> \"\$SHIM_LOG\"\necho >> \"\$SHIM_LOG\"\nexit \"\${FAKE_DOCKER_EXIT:-0}\"");
        $this->shim('df', "echo 'Filesystem 1K-blocks Used Available Use% Mounted on'\necho \"/dev/sda1 1000 500 500 \${FAKE_DISK:-10}% /\"");
        $this->shim('free', "echo '       total used free'\necho \"Mem: 1000 \$(( \${FAKE_MEM:-10} * 10 )) 0\"");
        file_put_contents($this->dir . '/app/scripts/health-check.sh', <<<'SH'
            #!/bin/sh
            echo "$@" > "$HEALTH_ARGS_FILE"
            [ "${FAKE_HEALTH:-0}" = "0" ] && exit 0
            echo "Health check FALLÓ: simulado HTTP 503" >&2
            exit 1
            SH);
        chmod($this->dir . '/app/scripts/health-check.sh', 0755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testEverythingHealthyDoesNothing(): void
    {
        [$exit, $out] = $this->monitor();

        $this->assertSame(0, $exit);
        $this->assertSame('', $out, 'Debe ser silencioso: cron mandaría cualquier salida a un correo local que no existe.');
        $this->assertSame([], $this->alertCalls());
        $this->assertSame([], $this->stateKeys());
    }

    public function testChecksTheApiOverHttpsWithTheRealDomainResolvedToTheVps(): void
    {
        $this->monitor();

        $this->assertSame(
            'https://api.example.test/health/ready api.example.test:443:127.0.0.1',
            trim((string) file_get_contents($this->dir . '/health-args')),
        );
    }

    public function testApiDownSendsOneAlertByEmailViaTheApp(): void
    {
        $this->monitor(['FAKE_HEALTH' => '1']);

        $calls = $this->alertCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('compose', $calls[0]);
        $this->assertStringContainsString($this->dir . '/app/docker-compose.vps.yaml', $calls[0]);
        $this->assertStringContainsString($this->dir . '/app/docker-compose.vps.prod.yaml', $calls[0]);
        // `run --no-deps`: sale aunque el contenedor php-fpm esté caído, que es justo cuando hace falta avisar.
        $this->assertStringContainsString('run --rm -T --no-deps php-fpm php bin/console app:monitor:alert --to ' . self::MAILTO, $calls[0]);
        $this->assertStringContainsString('ALERTA API', $calls[0]);
        $this->assertStringContainsString('api.example.test', $calls[0]);
        $this->assertStringContainsString('HTTP 503', $calls[0], 'El cuerpo incluye el motivo que dio el health check.');
        $this->assertSame(['api'], $this->stateKeys());
        $this->assertStringContainsString('ALERTA', $this->log());
    }

    public function testDoesNotRepeatTheAlertEveryCycle(): void
    {
        $this->monitor(['FAKE_HEALTH' => '1']);
        $this->monitor(['FAKE_HEALTH' => '1']);
        $this->monitor(['FAKE_HEALTH' => '1']);

        $this->assertCount(1, $this->alertCalls());
    }

    public function testEveryCycleActuallyRunsEvenAfterAnAlertWasSent(): void
    {
        // Guarda contra un bloqueo (flock) que un proceso hijo mantenga abierto y haga que
        // los ciclos siguientes salgan en silencio: sin esto "no repite el aviso" pasaría
        // por la razón equivocada.
        $this->monitor(['FAKE_HEALTH' => '1']);
        $this->monitor(['FAKE_HEALTH' => '1']);
        $this->monitor(['FAKE_HEALTH' => '1']);

        $this->assertSame(3, substr_count($this->log(), 'PROBLEMA [api]'), 'Cada ciclo debe comprobar y registrar el problema.');
    }

    public function testRepeatsTheAlertAfterTheRepeatInterval(): void
    {
        $this->setState('api', time() - 7200); // 2 h; el intervalo por defecto es 60 min

        $this->monitor(['FAKE_HEALTH' => '1']);

        $this->assertCount(1, $this->alertCalls());
    }

    public function testDoesNotRepeatWithinTheRepeatInterval(): void
    {
        $this->setState('api', time() - 600);

        $this->monitor(['FAKE_HEALTH' => '1']);

        $this->assertSame([], $this->alertCalls());
    }

    public function testSendsARecoveryNoticeAndClearsTheState(): void
    {
        $this->setState('api', time() - 600);

        $this->monitor(); // vuelve a estar sano

        $calls = $this->alertCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('RECUPERADO', $calls[0]);
        $this->assertSame([], $this->stateKeys());
    }

    public function testFailureToSendKeepsTheIncidentPendingSoItRetriesNextCycle(): void
    {
        $this->monitor(['FAKE_HEALTH' => '1', 'FAKE_DOCKER_EXIT' => '1']);

        $this->assertSame([], $this->stateKeys(), 'Si el aviso no salió no se marca como enviado.');
        $this->assertStringContainsString('no se pudo enviar', $this->log());

        $this->monitor(['FAKE_HEALTH' => '1']); // ahora el correo sí sale

        $this->assertCount(2, $this->alertCalls(), 'Reintenta en el ciclo siguiente.');
        $this->assertSame(['api'], $this->stateKeys());
    }

    public function testHighDiskUsageAlerts(): void
    {
        $this->monitor(['FAKE_DISK' => '91']);

        $calls = $this->alertCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('ALERTA DISCO', $calls[0]);
        $this->assertStringContainsString('91%', $calls[0]);
        $this->assertSame(['disk'], $this->stateKeys());
    }

    public function testDiskAtTheThresholdDoesNotAlert(): void
    {
        $this->monitor(['FAKE_DISK' => '85']);

        $this->assertSame([], $this->alertCalls());
    }

    public function testHighMemoryUsageAlerts(): void
    {
        $this->monitor(['FAKE_MEM' => '95']);

        $calls = $this->alertCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('ALERTA RAM', $calls[0]);
        $this->assertStringContainsString('95%', $calls[0]);
    }

    public function testIndependentProblemsAlertIndependently(): void
    {
        $this->monitor(['FAKE_HEALTH' => '1', 'FAKE_DISK' => '99']);

        $this->assertCount(2, $this->alertCalls());
        $this->assertEqualsCanonicalizing(['api', 'disk'], $this->stateKeys());
    }

    public function testMissingDomainIsReportedInsteadOfCrashing(): void
    {
        file_put_contents($this->dir . '/app/.env.vps', "POSTGRES_USER=x\n");

        [$exit] = $this->monitor();

        $this->assertSame(0, $exit);
        $calls = $this->alertCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('DOMAIN', $calls[0]);
    }

    public function testScriptIsExecutableWithABashShebang(): void
    {
        $script = self::ROOT . '/monitor.sh';

        $this->assertTrue(is_executable($script));
        $this->assertStringStartsWith('#!/usr/bin/env bash', (string) file_get_contents($script));
    }

    /**
     * @param array<string, string> $env
     *
     * @return array{int, string} código de salida y stdout+stderr
     */
    private function monitor(array $env = []): array
    {
        $process = proc_open(
            ['sh', self::ROOT . '/monitor.sh'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + [
                'PATH' => $this->dir . '/bin:/usr/bin:/bin',
                'HOME' => $this->dir,
                'MONITOR_APP_DIR' => $this->dir . '/app',
                'MONITOR_STATE_DIR' => $this->dir . '/state',
                'MONITOR_LOG_FILE' => $this->dir . '/logs/monitor.log',
                'MONITOR_MAILTO' => self::MAILTO,
                'SHIM_LOG' => $this->dir . '/docker-calls',
                'HEALTH_ARGS_FILE' => $this->dir . '/health-args',
            ],
        );
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        return [proc_close($process), $out];
    }

    private function shim(string $name, string $body): void
    {
        file_put_contents($this->dir . '/bin/' . $name, "#!/bin/sh\n" . $body . "\n");
        chmod($this->dir . '/bin/' . $name, 0755);
    }

    /** @return list<string> */
    private function alertCalls(): array
    {
        $file = $this->dir . '/docker-calls';

        return is_file($file) ? array_values(array_filter(file($file, FILE_IGNORE_NEW_LINES) ?: [])) : [];
    }

    /** @return list<string> */
    private function stateKeys(): array
    {
        $keys = array_values(array_diff(scandir($this->dir . '/state') ?: [], ['.', '..', 'lock']));
        sort($keys);

        return $keys;
    }

    private function setState(string $key, int $epoch): void
    {
        file_put_contents($this->dir . '/state/' . $key, (string) $epoch);
    }

    private function log(): string
    {
        $file = $this->dir . '/logs/monitor.log';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
