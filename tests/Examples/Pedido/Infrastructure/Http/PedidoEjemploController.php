<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controlador de ejemplo de Infrastructure/Http. Los reales llevan #[AsController] y #[Route] y se
 * importan solos desde config/routes.yaml con el prefijo /dashboard/api.
 */
#[AsController]
final class PedidoEjemploController
{
    #[Route('/ejemplo-pedido/ping', name: 'ejemplo_pedido_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
