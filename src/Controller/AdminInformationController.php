<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RequestInfo;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationInfoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminInformationController extends AbstractController
{
    public function __construct(private readonly CommunicationInfoService $infoService)
    {
    }

    #[Route('/information', name: 'admin_information', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Consultar información de venta al proveedor', tag: 'Admin Information', requestDto: RequestInfo::class)]
    public function index(RequestInfo $requestInfo): JsonResponse
    {
        return $this->json(
            $this->infoService->querySale($requestInfo)
        );
    }
}
