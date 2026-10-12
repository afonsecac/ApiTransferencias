<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guardas de configuración para el código nuevo (ADR-001):
 *  - las carpetas Domain no se registran como servicios;
 *  - ninguna ruta queda sin regla de acceso (denegación por defecto).
 */
final class NewCodeConfigurationTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private static function yaml(string $relative): array
    {
        $data = Yaml::parseFile(self::root() . '/' . $relative);
        self::assertIsArray($data);

        return $data;
    }

    public function testDomainFoldersAreNotRegisteredAsServices(): void
    {
        $services = self::yaml('config/services.yaml')['services'];
        $excluded = $services['App\\']['exclude'] ?? [];

        self::assertContains('../src/Market/*/Domain/', $excluded);
        self::assertContains('../src/Delivery/*/Domain/', $excluded);
    }

    public function testAccessControlEndsWithDenyByDefault(): void
    {
        $rules = self::yaml('config/packages/security.yaml')['security']['access_control'];
        $last = end($rules);

        self::assertSame('^/', $last['path'] ?? null, 'La última regla debe ser la denegación por defecto.');
        self::assertSame('ROLE_DENY_BY_DEFAULT', $last['roles'] ?? null);
    }

    /**
     * Toda ruta fuera de /api y /dashboard/api debe estar cubierta por una regla pública explícita.
     * Si alguien añade un prefijo nuevo (p. ej. /seller/api) sin regla, queda denegado por defecto
     * y este test obliga a decidirlo.
     */
    public function testRoutesOutsideKnownPrefixesHaveAnExplicitRule(): void
    {
        $rules = self::yaml('config/packages/security.yaml')['security']['access_control'];
        $explicit = array_values(array_filter(
            array_map(static fn (array $r): string => (string) ($r['path'] ?? ''), $rules),
            static fn (string $p): bool => $p !== '^/' && $p !== '' && !str_starts_with($p, '^/api') && !str_starts_with($p, '^/dashboard/api'),
        ));

        $uncovered = [];
        foreach (self::allRoutePaths() as $path) {
            if (str_starts_with($path, '/api') || str_starts_with($path, '/dashboard/api') || str_starts_with($path, '/_')) {
                continue;
            }
            $covered = false;
            foreach ($explicit as $pattern) {
                if (preg_match('#' . $pattern . '#', $path) === 1) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $uncovered[] = $path;
            }
        }

        self::assertSame([], $uncovered, 'Rutas sin regla de acceso explícita: ' . implode(', ', $uncovered));
    }

    /**
     * @return list<string>
     */
    private static function allRoutePaths(): array
    {
        $kernel = new \App\Kernel('test', true);
        $kernel->boot();
        $router = $kernel->getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $paths = [];
        foreach ($router->getRouteCollection() as $route) {
            $paths[] = $route->getPath();
        }
        $kernel->shutdown();

        return $paths;
    }
}
