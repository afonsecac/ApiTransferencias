<?php

declare(strict_types=1);

namespace App\Tests\Examples\Mercado\Infrastructure\Http;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Solo en test: devuelve los roles con los que el firewall de /api autenticó la petición. Permite comprobar,
 * de punta a punta, qué cuentas reciben ROLE_MARKET_API_USER. Los endpoints de mercado reales exigirán ese rol.
 */
#[AsController]
final class RolesEjemploController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/api/_ejemplo/roles', name: 'ejemplo_mercado_roles', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $roles = $this->security->getUser()?->getRoles() ?? [];
        sort($roles);

        return new JsonResponse(['roles' => $roles]);
    }
}
