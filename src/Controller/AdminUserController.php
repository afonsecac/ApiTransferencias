<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ResendActivationOutDto;
use App\DTO\Out\UserOutDto;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UserService;
use App\Util\DashboardUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/user")]
#[IsGranted("ROLE_SYSTEM_ADMIN")]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RateLimiterFactory $resendActivationLimiter,
    ) {
    }

    #[Route('/all')]
    #[DashboardEndpoint(summary: 'Listar todos los usuarios', tag: 'Admin Users', responseDto: PaginatedListOutDto::class, itemDto: UserOutDto::class)]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page');
        $limit = $request->query->getInt('limit', DashboardUtil::$LIMIT_DEFAULT);

        return $this->json([
            $this->userService->allUsers($page, $limit),
        ]);
    }

    #[Route('/{id}/resend-activation', name: 'admin_user_resend_activation', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Reenviar email de activación de cuenta', tag: 'Admin Users', responseDto: ResendActivationOutDto::class, responseStatusCode: 200)]
    public function resendActivation(int $id): JsonResponse
    {
        $limiter = $this->resendActivationLimiter->create('user_' . $id);
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => ['message' => 'Too many requests. Please try again later.'],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = $this->userService->findById($id);
        if ($user === null) {
            return $this->json(
                ['error' => ['message' => 'User not found']],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->userService->dispatchActivationEmail($user);
        } catch (MyCurrentException $e) {
            return $this->json(
                ['error' => ['message' => $e->getMessage()]],
                $e->getCode() === 409 ? Response::HTTP_CONFLICT : $e->getCode()
            );
        }

        return $this->json(['resent' => true]);
    }
}
