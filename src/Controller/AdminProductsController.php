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
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/admin/products')]
class AdminProductsController extends AbstractController
{
    private ProductBenefitService $prdBenService;
    private CommunicationProductService $prodService;
    private NormalizerInterface $serializer;

    /**
     * @param \App\Service\ProductBenefitService $productBenefitService
     * @param \App\Service\CommunicationProductService $prdService
     */
    public function __construct(ProductBenefitService $productBenefitService, CommunicationProductService $prdService, NormalizerInterface $serializer)
    {
        $this->prdBenService = $productBenefitService;
        $this->prodService = $prdService;
        $this->serializer = $serializer;
    }

    #[Route('', name: 'admin_products', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar productos', tag: 'Admin Products', responseDto: PaginatedListOutDto::class)]
    public function products(Request $request): JsonResponse
    {
        $page = max(0, $request->query->getInt('page', 0));
        $limit = min(100, max(1, $request->query->getInt('limit', 10)));

        $paginated = $this->prodService->getProducts(
            $page,
            $limit,
            $request->query->get('environmentType'),
            $request->query->get('search'),
            $request->query->get('provider'),
            $request->query->getString('orderBy', 'price ASC'),
        );

        // Mismo sobre y mismas columnas que GET /products (DashboardCatalogController):
        // el dashboard consume ambos con el mismo tipo.
        return $this->json([
            'limit' => $paginated->getLimit(),
            'currentPage' => $paginated->getPage(),
            'hasNext' => $paginated->getPage() + 1 < $paginated->getTotalPages(),
            'hasPrevious' => $paginated->getPage() > 0,
            'results' => $this->serializer->normalize($paginated->getResults(), 'json', [
                'groups' => ['product:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]),
        ]);
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
