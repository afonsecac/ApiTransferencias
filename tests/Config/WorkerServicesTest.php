<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guarda contra el olvido más caro de los workers: un transporte (o un worker nuevo) que existe en la
 * configuración pero que ningún proceso consume o que el despliegue no recrea. Los eventos del outbox
 * (`domain_events`) quedarían encolados sin procesarse y nadie se enteraría.
 */
final class WorkerServicesTest extends TestCase
{
    private const COMPOSE_FILES = [
        'docker-compose.yaml',
        'docker-compose.vps.staging.yaml',
        'docker-compose.vps.prod.yaml',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $relative): string
    {
        $content = file_get_contents(self::root() . '/' . $relative);
        self::assertIsString($content);

        return $content;
    }

    /**
     * @return array<string, string> servicio worker => comando de consumo normalizado
     */
    private static function workerCommands(string $composeFile): array
    {
        $compose = Yaml::parseFile(self::root() . '/' . $composeFile);
        self::assertIsArray($compose);

        $commands = [];
        foreach ($compose['services'] as $name => $service) {
            if ('worker' === $name || str_starts_with((string) $name, 'worker-')) {
                $commands[(string) $name] = (string) preg_replace('/\s+/', ' ', (string) ($service['command'] ?? ''));
            }
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    private static function transports(): array
    {
        $messenger = Yaml::parseFile(self::root() . '/config/packages/messenger.yaml');
        self::assertIsArray($messenger);

        return array_values(array_diff(array_keys($messenger['framework']['messenger']['transports']), ['failed']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function composeFiles(): iterable
    {
        foreach (self::COMPOSE_FILES as $file) {
            yield $file => [$file];
        }
    }

    /**
     * @dataProvider composeFiles
     */
    public function testEveryMessengerTransportHasAConsumer(string $composeFile): void
    {
        $commands = implode("\n", self::workerCommands($composeFile));

        foreach (self::transports() as $transport) {
            self::assertMatchesRegularExpression(
                '/messenger:consume[^\n]*\b' . preg_quote($transport, '/') . '\b/',
                $commands,
                sprintf('Ningún worker de %s consume el transporte "%s".', $composeFile, $transport),
            );
        }
    }

    public function testProdHasADedicatedEventsWorker(): void
    {
        $commands = self::workerCommands('docker-compose.vps.prod.yaml');

        self::assertArrayHasKey('worker-events', $commands);
        self::assertStringContainsString('messenger:consume domain_events', $commands['worker-events']);
    }

    public function testProdDeployRecreatesEveryWorker(): void
    {
        $workflow = (string) preg_replace('/\\\\\s+/', ' ', self::read('.github/workflows/deploy-prod.yaml'));

        foreach (array_keys(self::workerCommands('docker-compose.vps.prod.yaml')) as $worker) {
            self::assertMatchesRegularExpression(
                '/up -d [^\n]*\b' . preg_quote($worker, '/') . '\b/',
                $workflow,
                sprintf('deploy-prod.yaml no recrea %s: quedaría con la imagen anterior.', $worker),
            );
        }
    }

    public function testDeployScriptStopsEveryProdWorkerGracefully(): void
    {
        $script = self::read('deploy.sh');
        self::assertSame(1, preg_match('/WORKER_SERVICES="(worker-[^"]+)"/', $script, $matches));

        foreach (array_keys(self::workerCommands('docker-compose.vps.prod.yaml')) as $worker) {
            self::assertStringContainsString($worker, $matches[1], sprintf('deploy.sh no detiene %s con SIGTERM.', $worker));
        }
    }

    public function testPostgresUpgradeStopsEveryProdWorker(): void
    {
        $script = self::read('scripts/upgrade-postgres.sh');

        foreach (array_keys(self::workerCommands('docker-compose.vps.prod.yaml')) as $worker) {
            self::assertStringContainsString($worker, $script, sprintf('upgrade-postgres.sh no para %s.', $worker));
        }
    }
}
