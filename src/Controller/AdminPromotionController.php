<?php

namespace App\Controller;

use App\DTO\CreateAdminPromotionDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/admin/promotion')]
class AdminPromotionController extends AbstractController
{
    /**
     * Fase 2 de la deprecación del catálogo V1 (ver plan en memoria del
     * proyecto): el alta de promociones V1 queda cerrada — usar
     * POST /promotions/v2 (DashboardPromotionController::createV2()).
     */
    #[Route('/create', name: 'admin_promotion_index', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear promoción (V1, deshabilitado — usar /promotions/v2)', tag: 'Admin Promotions', requestDto: CreateAdminPromotionDto::class, responseStatusCode: 201)]
    public function index(CreateAdminPromotionDto $dto): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => 'La creación de promociones V1 está deshabilitada — usá el alta V2 (POST /promotions/v2).', 'code' => 'V1_PROMOTION_CREATION_DISABLED']],
            Response::HTTP_GONE,
        );
    }
}
