<?php

namespace App\Controller;

use App\DTO\CreateAdminPromotionDto;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationPromotionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/admin/promotion')]
class AdminPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionService $service,
    ) {
    }

    #[Route('/create', name: 'admin_promotion_index', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear promoción (admin)', tag: 'Admin Promotions', requestDto: CreateAdminPromotionDto::class, responseStatusCode: 201)]
    public function index(CreateAdminPromotionDto $dto): JsonResponse
    {
        try {
            $promotion = $this->service->createFromDto($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($promotion, Response::HTTP_CREATED);
    }
}
