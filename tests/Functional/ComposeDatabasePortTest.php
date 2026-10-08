<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Postgres nunca debe publicarse en una interfaz pública. En producción se
 * publica solo en el loopback del VPS (127.0.0.1:5433) para entrar por túnel
 * SSH; este test fija que esa configuración vive en el repositorio (el servidor
 * la tenía solo en un commit local) y que ningún compose expone la BD al exterior.
 *
 * @coversNothing
 */
class ComposeDatabasePortTest extends TestCase
{
    private const FILES = [
        'docker-compose.vps.yaml',
        'docker-compose.vps.staging.yaml',
        'docker-compose.vps.prod.yaml',
    ];

    public function testProductionPublishesPostgresOnlyOnTheVpsLoopback(): void
    {
        $prod = Yaml::parseFile($this->path('docker-compose.vps.prod.yaml'));

        $this->assertSame(['127.0.0.1:5433:5432'], $prod['services']['database']['ports'] ?? null);
    }

    /** @return iterable<string, array{string}> */
    public static function composeFiles(): iterable
    {
        foreach (self::FILES as $file) {
            yield $file => [$file];
        }
    }

    /** @dataProvider composeFiles */
    public function testNoComposeFileExposesThePostgresPortPublicly(string $file): void
    {
        $compose = Yaml::parseFile($this->path($file));

        $outsideLoopback = array_values(array_filter(
            $compose['services']['database']['ports'] ?? [],
            static fn (mixed $port): bool => !str_starts_with((string) $port, '127.0.0.1:'),
        ));

        $this->assertSame([], $outsideLoopback, sprintf('%s publica Postgres fuera del loopback', $file));
    }

    public function testMonitorScriptIsVersionedAndExecutable(): void
    {
        $script = $this->path('monitor.sh');

        $this->assertFileExists($script, 'cron del VPS lo ejecuta desde /opt/api-transferencias/monitor.sh');
        $this->assertTrue(is_executable($script));
        $this->assertStringStartsWith('#!/usr/bin/env bash', (string) file_get_contents($script));
    }

    private function path(string $file): string
    {
        return dirname(__DIR__, 2) . '/' . $file;
    }
}
