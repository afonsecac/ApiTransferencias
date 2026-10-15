<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\GetAiStatus;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Si la asistencia con IA del catálogo está habilitada (COMR-277). El controlador solo traduce HTTP.
 */
#[AsController]
#[Route('/market/ai-status')]
#[IsGranted('ROLE_API_ADMIN')]
final class AiStatusController
{
    public function __construct(private readonly GetAiStatus $status)
    {
    }

    #[Route('', name: 'market_ai_status', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Si la asistencia con IA del catálogo está habilitada (apagada por defecto)', tag: 'Market', responseDto: AiStatusOutDto::class)]
    public function get(): JsonResponse
    {
        return new JsonResponse(new AiStatusOutDto(($this->status)()));
    }
}
