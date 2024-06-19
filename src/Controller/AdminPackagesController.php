<?php

namespace App\Controller;

use App\Service\CommunicationPackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/packages')]
class AdminPackagesController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPackageService $packagesPricesService
    )
    {

    }
    #[Route('/all', name: 'admin_packages_all', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $productId = $request->query->getInt('productId');
        $tenantId = $request->query->get('tenantId');
        return $this->json(
            $this->packagesPricesService->all(
                $productId,
                $tenantId ? (int) $tenantId : null
            )
        );
    }
}
