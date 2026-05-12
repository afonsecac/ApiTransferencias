<?php

namespace App\Controller;

use App\DTO\TwoFactorConfigDto;
use App\DTO\Out\TwoFactorConfigOutDto;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\TwoFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/2fa')]
class AdminTwoFactorConfigController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
    ) {}

    #[Route('/config', name: 'admin_2fa_config_get', methods: ['GET'])]
    #[DashboardEndpoint(
        summary: 'Obtener configuración global del 2FA',
        description: 'Devuelve la política de 2FA activa: modo (`optional`/`mandatory`), método de verificación (`totp`/`email`) y fecha límite de obligatoriedad. Solo accesible para `ROLE_SUPER_ADMIN`.',
        tag: '2FA Admin',
        responseDto: TwoFactorConfigOutDto::class,
    )]
    public function getConfig(): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => ['message' => 'Access denied']], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->twoFactorService->getConfig());
    }

    #[Route('/config', name: 'admin_2fa_config_update', methods: ['PATCH'])]
    #[DashboardEndpoint(
        summary: 'Actualizar configuración global del 2FA',
        description: 'Modifica la política de 2FA. Los tres campos son opcionales; solo se actualizan los enviados. **Efecto secundario importante:** si el resultado final es `mode=mandatory` con un `deadline` definido, se despachan automáticamente emails de aviso a todos los usuarios activos que aún no tengan el 2FA activado. Solo accesible para `ROLE_SUPER_ADMIN`.',
        tag: '2FA Admin',
        requestDto: TwoFactorConfigDto::class,
        responseDto: TwoFactorConfigOutDto::class,
    )]
    public function updateConfig(TwoFactorConfigDto $dto): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => ['message' => 'Access denied']], Response::HTTP_FORBIDDEN);
        }

        try {
            $config = $this->twoFactorService->updateConfig($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($config);
    }
}
