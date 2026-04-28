<?php

namespace App\Controller;

use App\DTO\Out\CompanyRefOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\ClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client')]
class AdminClientController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clientService
    ) {
    }

    #[Route('/all', name: 'admin_client_all', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar todos los clientes', tag: 'Admin Clients', responseDto: PaginatedListOutDto::class, itemDto: CompanyRefOutDto::class)]
    public function index(
        #[MapQueryParameter] int    $page = 0,
        #[MapQueryParameter] int    $limit = 10,
        #[MapQueryParameter] string $orderBy = 'companyName',
        #[MapQueryParameter] string $direction = 'ASC',
    ): JsonResponse {
        return $this->json($this->clientService->getAllClients([
            'page'      => $page,
            'limit'     => $limit,
            'orderBy'   => $orderBy,
            'direction' => $direction,
        ]));
    }
}
