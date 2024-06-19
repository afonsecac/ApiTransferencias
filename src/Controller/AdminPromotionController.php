<?php

namespace App\Controller;

use App\Service\CommunicationPromotionService;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/promotion')]
class AdminPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionService $service
    )
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/create', name: 'admin_promotion_index', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        return $this->json(
            $this->service->onCreatedPromotion($request->request->all())
        );
    }
}
