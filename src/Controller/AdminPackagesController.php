<?php

namespace App\Controller;

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

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    #[Route('/take', name: 'admin_packages_take_products', methods: ['GET'])]
    public function takeProducts(Request $request): JsonResponse
    {
        $env = $request->query->get('env', 'TEST');
        return $this->json($this->productService->takeProduct($env));
    }
}
