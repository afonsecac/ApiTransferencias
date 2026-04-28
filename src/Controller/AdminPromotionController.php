<?php

namespace App\Controller;

use App\DTO\CreateAdminPromotionDto;
use App\Exception\MyCurrentException;
use App\Service\CommunicationPromotionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/promotion')]
class AdminPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionService $service,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/create', name: 'admin_promotion_index', methods: ['POST'])]
    public function index(CreateAdminPromotionDto $dto): JsonResponse
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(
                ['error' => ['message' => 'Validation failed', 'details' => $details]],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $promotion = $this->service->createFromDto($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($promotion, Response::HTTP_CREATED);
    }
}
