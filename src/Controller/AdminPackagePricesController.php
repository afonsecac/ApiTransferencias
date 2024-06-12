<?php

namespace App\Controller;

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

    #[Route('/{packageId}', name: 'admin_package_prices', methods: ['GET'])]
    public function pricesByPackages(Request $request): JsonResponse
    {
        $packageId = $request->query->getInt('packageId');

        return $this->json(
            $this->packagePriceService->getUnusedPrices($packageId)
        );
    }
}
