<?php

namespace App\Controller;

use App\Entity\User;
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

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    #[Route('', name: 'admin_profile', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->getUser());
    }

    /**
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonEncodingException
     */
    #[Route('', name: 'admin_profile_update', methods: ['PUT', 'PATCH'])]
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