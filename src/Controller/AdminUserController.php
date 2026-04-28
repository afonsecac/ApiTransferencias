<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\UserOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UserService;
use App\Util\DashboardUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/user")]
#[IsGranted("ROLE_SYSTEM_ADMIN")]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService
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
}
