<?php

namespace App\Controller;

use App\DTO\Out\InsertResultOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationPackageService;
use App\Service\TakeProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/packages')]
class AdminPackagesController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPackageService $packagesPricesService,
        private readonly TakeProductService $productService
    )
    {

    }
    #[Route('/all', name: 'admin_packages_all', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar todos los paquetes', tag: 'Admin Packages', responseIsArray: true)]
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

    #[Route('/take', name: 'admin_packages_take_products', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Importar productos del proveedor', tag: 'Admin Packages', responseDto: InsertResultOutDto::class)]
    public function takeProducts(Request $request): JsonResponse
    {
        $env = $request->query->get('env', 'TEST');
        return $this->json($this->productService->takeProduct($env));
    }
}
