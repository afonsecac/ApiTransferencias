<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure\Http;

use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convención para Infrastructure/Http: los controladores de src/Market/<Contexto>/Infrastructure/Http y
 * src/Delivery/<Contexto>/Infrastructure/Http se importan solos, bajo /dashboard/api, así que quedan detrás
 * del firewall del dashboard. Este ejemplo (solo en test) demuestra que la importación funciona y que
 * una petición sin autenticar se rechaza.
 */
final class PedidoEjemploControllerTest extends FunctionalTestCase
{
    public function testExampleControllerRouteIsImportedUnderTheDashboardPrefix(): void
    {
        $router = self::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $route = $router->getRouteCollection()->get('ejemplo_pedido_ping');

        self::assertNotNull($route, 'Las rutas de Infrastructure/Http no se importan.');
        self::assertSame('/dashboard/api/ejemplo-pedido/ping', $route->getPath());
    }

    public function testRequestWithoutCredentialsIsRejected(): void
    {
        $response = $this->handle('/dashboard/api/ejemplo-pedido/ping');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * Una ruta existente fuera de /api y /dashboard/api y sin regla pública explícita no es accesible
     * sin autenticar (el router responde 404 a rutas inexistentes antes de llegar al firewall, por eso
     * la prueba usa una ruta real).
     */
    public function testRouteOutsideTheKnownPrefixesIsDeniedByDefault(): void
    {
        $response = $this->handle('/_ejemplo-pedido/fuera-de-prefijo');

        self::assertContains($response->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]);
        self::assertNotSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function handle(string $uri): Response
    {
        return self::$kernel->handle(Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    }
}
