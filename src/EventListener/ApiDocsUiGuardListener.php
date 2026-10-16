<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Oculta la interfaz web (Swagger UI) de /api/docs y de /dashboard/api/docs cuando API_DOCS_UI=0.
 *
 * El JSON de OpenAPI (/dashboard/api/docs.json, /api/docs.jsonopenapi, .json, .jsonld y /api/docs con
 * Accept distinto de text/html) NO se toca: sigue disponible para Postman,
 * generadores de clientes y el consumidor de la API.
 *
 * Se hace en tiempo de ejecución y no con `enable_swagger_ui` porque API
 * Platform lee esa opción al compilar el contenedor, y staging y producción
 * comparten APP_ENV=prod: una condición por entorno de Symfony cerraría las
 * dos a la vez.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
class ApiDocsUiGuardListener
{
    public function __construct(
        #[Autowire('%env(bool:API_DOCS_UI)%')]
        private readonly bool $uiEnabled,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if ($this->uiEnabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = rtrim($request->getPathInfo(), '/');

        // La interfaz del dashboard es siempre HTML (su definición está en /dashboard/api/docs.json y no se toca).
        if ('/api/docs.html' === $path
            || '/dashboard/api/docs' === $path
            || ('/api/docs' === $path && str_contains((string) $request->headers->get('Accept'), 'text/html'))
        ) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
