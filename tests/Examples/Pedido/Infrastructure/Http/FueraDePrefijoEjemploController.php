<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ruta fuera de /api y /dashboard/api y sin regla pública: solo la deniega la regla por defecto.
 * Existe únicamente en test (el prefijo `/_` queda fuera del guardián de rutas sin regla).
 */
#[AsController]
final class FueraDePrefijoEjemploController
{
    #[Route('/_ejemplo-pedido/fuera-de-prefijo', name: 'ejemplo_pedido_fuera_de_prefijo', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
