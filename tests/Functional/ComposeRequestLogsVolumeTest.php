<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * El respaldo de los POST de venta debe sobrevivir a los despliegues: cada
 * despliegue recrea el contenedor de php-fpm (--force-recreate), así que el
 * directorio de los request logs tiene que ser un volumen con nombre.
 *
 * @coversNothing
 */
class ComposeRequestLogsVolumeTest extends TestCase
{
    private const MOUNT = '/var/www/var/log/requests';

    public function testPhpFpmMountsANamedVolumeOnTheRequestLogsDirectory(): void
    {
        $compose = Yaml::parseFile(dirname(__DIR__, 2) . '/docker-compose.vps.yaml');

        $this->assertContains('request_logs:' . self::MOUNT, $compose['services']['php-fpm']['volumes'] ?? []);
        $this->assertArrayHasKey('request_logs', $compose['volumes']);
        $this->assertFalse(
            isset($compose['volumes']['request_logs']['external']),
            'Debe crearlo compose; no depender de un volumen externo preexistente.',
        );
    }

    public function testTheHandlerWritesInsideThatSameDirectory(): void
    {
        $services = (string) file_get_contents(dirname(__DIR__, 2) . '/config/services.yaml');

        $this->assertMatchesRegularExpression('#\$filename:\s*\'%kernel\.logs_dir%/requests/request\.%kernel\.environment%\.log\'#', $services);
    }

    public function testTheImageCreatesTheDirectoryOwnedByWwwData(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__, 2) . '/docker/php-fpm/Dockerfile');

        $mkdir = strpos($dockerfile, 'mkdir -p var/log/requests');
        $chown = strpos($dockerfile, 'chown -R www-data:www-data var/');
        $this->assertNotFalse($mkdir, 'El directorio debe existir en la imagen para que el volumen herede el propietario.');
        $this->assertLessThan($chown, $mkdir, 'mkdir antes del chown, o el directorio queda de root.');
    }
}
