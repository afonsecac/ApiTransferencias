<?php

namespace App\Controller;

use App\DTO\ForgotPassword;
use App\DTO\ResetPassword;
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
        $user->setCurrentIp($clientIp);
        $token = $this->userService->createToken($user, null);

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
        $newToken = $this->userService->createToken($user, null);
        $myUser = $this->userService->createPayloadUser($user);

        return $this->json([
            'token' => $newToken,
            'user' => $myUser,
        ]);
    }

    #[Route('/forgot-password', name: 'app_dashboard_reset', methods: ['POST'])]
    public function forgot(ForgotPassword $forgotPassword): JsonResponse
    {
        $response = $this->userService->forgotPassword($forgotPassword);

        return $this->json($response, $response['status'] ?? Response::HTTP_OK);
    }

    #[Route('/reset-password', name: 'app_dashboard_reset_password', methods: ['POST'])]
    public function reset(ResetPassword $resetPassword): JsonResponse
    {
        $response = $this->userService->resetPassword($resetPassword);
        return $this->json($response, $response['status'] ?? Response::HTTP_OK);
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
