<?php

namespace App\Controller;

use App\Service\ClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client')]
class AdminClientController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clientService
    ) {

    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    #[Route('/all', name: 'admin_client_all', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        return $this->json($this->clientService->getAllClients($request->query->all()));
    }
}
