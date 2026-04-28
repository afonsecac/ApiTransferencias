<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Out\UserOutDto;
use App\Entity\User;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/auth")]
class AdminAuthController extends AbstractController
{

    public function __construct(
        private readonly UserService $userService
    )
    {
    }

    #[Route('/profile')]
    #[IsGranted("ROLE_SYSTEM_USER")]
    #[DashboardEndpoint(summary: 'Perfil del usuario autenticado', tag: 'Auth', responseDto: UserOutDto::class)]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => [
                    'message' => 'User is not authenticated.',
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
        return $this->json(
            $this->userService->createPayloadUser($user)
        );
    }
}
