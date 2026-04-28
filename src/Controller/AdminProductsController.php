<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\SuccessOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationProductService;
use App\Service\ProductBenefitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin/products')]
class AdminProductsController extends AbstractController
{
    private ProductBenefitService $prdBenService;
    private CommunicationProductService $prodService;

    /**
     * @param \App\Service\ProductBenefitService $productBenefitService
     * @param \App\Service\CommunicationProductService $prdService
     */
    public function __construct(ProductBenefitService $productBenefitService, CommunicationProductService $prdService)
    {
        $this->prdBenService = $productBenefitService;
        $this->prodService = $prdService;
    }

    #[Route('', name: 'admin_products', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar productos', tag: 'Admin Products', responseDto: PaginatedListOutDto::class)]
    public function products(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 0);
        $limit = $request->query->getInt('limit', 10);
        $query = $request->query->get('query');
        $env = $request->query->getString('env', 'TEST');

        return $this->json(
            $this->prodService->getProducts($page, $limit, $env, $query)
        );
    }

    #[Route('/import', name: 'import_product_benefits', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Importar beneficios de productos', tag: 'Admin Products', responseDto: SuccessOutDto::class)]
    public function index(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'results' =>
                $this->prdBenService->getImportProducts(),
        ]);
    }
}
