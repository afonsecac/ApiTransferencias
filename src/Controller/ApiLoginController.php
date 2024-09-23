<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiLoginController extends AbstractController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonEncodingException
     */
    #[Route('/login', name: 'app_dashboard_login', methods: ['POST'])]
    public function index(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp();
        if (is_null($user)) {
            return $this->json([
                "error" => [
                    'message' => "Not valid credentials",
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }
        $activeSession = $this->userService->getActiveSession($user->getId());
        if (!is_null($activeSession) && is_null($activeSession?->getClosedAt())) {
            return $this->json([
                "error" => [
                    'message' => "You have an active session.",
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }
        $user->setCurrentIp($clientIp);
        $token = $this->userService->createSession($user, $clientIp);
        return $this->json([
            'token' => $token,
            'user' => $this->userService->createPayloadUser($user),
        ]);
    }

    /**
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonEncodingException
     */
    #[Route('/refresh', name: 'app_dashboard_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp();
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                "error" => [
                    'message' => "Not valid credentials",
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }
        $user->setCurrentIp($clientIp);
        $activeSession = $this->userService->getActiveSession($user->getId());
        if (!is_null($activeSession)) {
            $this->userService->updateActiveSession($activeSession);
        }
        $newToken = $this->userService->createToken($user, $activeSession);
        $myUser = $this->userService->createPayloadUser($user);
        return $this->json([
            'token' => $newToken,
            'user' => $myUser,
        ]);
    }

    #[Route('/logout', name: 'app_dashboard_logout')]
    public function logout(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'logout' => $this->userService->closeMySession($user?->getId()),
        ]);
    }
}
