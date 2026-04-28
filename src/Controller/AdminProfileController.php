<?php

namespace App\Controller;

use App\DTO\Out\ProfileUpdateOutDto;
use App\DTO\Out\UserOutDto;
use App\Entity\User;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/admin/profile')]
class AdminProfileController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    #[Route('', name: 'admin_profile', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Obtener perfil', tag: 'Admin Profile', responseDto: UserOutDto::class)]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->getUser());
    }

    #[Route('', name: 'admin_profile_update', methods: ['PUT', 'PATCH'])]
    #[DashboardEndpoint(summary: 'Actualizar perfil', tag: 'Admin Profile', responseDto: ProfileUpdateOutDto::class)]
    public function update(User $user): JsonResponse
    {
        $currentUser = $this->userService->update($user);
        $newToken = $this->userService->createToken($currentUser, null);
        $myUser = $this->userService->createPayloadUser($currentUser);
        return $this->json([
            'token' => $newToken,
            'user' => $myUser,
        ]);
    }
}