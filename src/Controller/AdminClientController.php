<?php

namespace App\Controller;

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
