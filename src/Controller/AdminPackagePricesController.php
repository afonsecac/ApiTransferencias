<?php

namespace App\Controller;

use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\PackagePriceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/package-prices')]
class AdminPackagePricesController extends AbstractController
{

    /**
     * @var \App\Service\PackagePriceService
     */
    private PackagePriceService $packagePriceService;

    public function __construct(
        PackagePriceService $packagePriceService
    ) {
        $this->packagePriceService = $packagePriceService;
    }

    #[Route('/prices/{packageId}', name: 'admin_package_prices', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Precios disponibles por paquete', tag: 'Admin Package Prices', responseIsArray: true)]
    public function pricesByPackages(int $packageId, Request $request): JsonResponse
    {
        $clientId = $request->query->getInt('clientId');


        return $this->json(
            $this->packagePriceService->getUnusedPrices($packageId, $clientId ?: null)
        );
    }

    #[Route('/single', name: 'admin_package_prices_single', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Agregar precio de paquete (individual)', tag: 'Admin Package Prices')]
    public function addPackagePrice(Request $request): JsonResponse
    {
        $packageInfo = $request->request->all();
        return $this->json(
            $this->packagePriceService->addPackagePrices([
                $packageInfo
            ])
        );
    }

    #[Route('/multiple', name: 'admin_packages_prices_single', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Agregar precios de paquete (múltiple)', tag: 'Admin Package Prices')]
    public function addPackagesPrice(Request $request): JsonResponse
    {
        $packageInfo = $request->request->all();
        return $this->json(
            $this->packagePriceService->addPackagePrices([
                $packageInfo
            ])
        );
    }
}
