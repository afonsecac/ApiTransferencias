<?php

namespace App\Controller;

use App\DTO\CreatePromotionV2Dto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\SetPromotionProviderProductDto;
use App\DTO\UpdatePromotionDto;
use App\DTO\UpsertPromotionDto;
use App\Exception\MyCurrentException;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Repository\CommunicationPromotionsRepository;
use App\Service\CommunicationPromotionService;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPromotionBindingService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Service\Pricing\PromotionEquivalenceResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
#[Route('/promotions')]
class DashboardPromotionController extends AbstractController
{
    public function __construct(
        private readonly CommunicationPromotionsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly CommunicationPromotionService $promotionService,
        private readonly CommunicationPromotionBindingService $bindingService,
        private readonly CommunicationPromotionEquivalenceService $equivalenceService,
        private readonly CommunicationContractService $contractService,
    ) {
    }

    #[Route('', name: 'dashboard_promotions_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar promociones', tag: 'Promotions', responseDto: PaginatedListOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');
        $filters = [
            'search' => $request->query->get('search'),
            'environmentId' => $request->query->get('environmentId'),
            'active' => $request->query->get('active'),
        ];

        $result = $this->repository->findAllPaginated($page, $limit, $filters, $orderBy);

        $result['results'] = $this->serializer->normalize(
            $result['results'],
            'json',
            [
                'groups' => ['promotion:list'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]
        );

        return $this->json($result);
    }

    #[Route('/{id}', name: 'dashboard_promotions_show', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Obtener detalle de promoción', tag: 'Promotions')]
    public function show(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->normalizeDetail($promotion));
    }

    /**
     * Fase 2 de la deprecación del catálogo V1 (ver plan en memoria del
     * proyecto): el alta de promociones V1 queda cerrada — usar
     * POST /promotions/v2 (createV2()) en su lugar. Deliberadamente NO se
     * toca esta acción para promociones V1 YA EXISTENTES (edit/detalle
     * siguen funcionando igual): hay al menos una promoción V1 real, vigente
     * hasta 2026-09-01, todavía vendiéndose en producción (confirmado contra
     * staging y prod, no solo dev) — bloquear su edición o su venta
     * rompería una campaña activa. Solo se cierra la puerta de ENTRADA a
     * paquetes V1 nuevos.
     */
    #[Route('', name: 'dashboard_promotions_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Crear promoción (V1, deshabilitado — usar /v2)', tag: 'Promotions', requestDto: UpsertPromotionDto::class, responseStatusCode: 201)]
    public function create(UpsertPromotionDto $dto): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => 'La creación de promociones V1 está deshabilitada — usá el alta V2 (POST /promotions/v2).', 'code' => 'V1_PROMOTION_CREATION_DISABLED']],
            Response::HTTP_GONE,
        );
    }

    #[Route('/v2', name: 'dashboard_promotions_create_v2', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Crear promoción V2 (catálogo compartido)', tag: 'Promotions', requestDto: CreatePromotionV2Dto::class, responseStatusCode: 201)]
    public function createV2(CreatePromotionV2Dto $dto): JsonResponse
    {
        try {
            $result = $this->promotionService->createV2($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'promotion' => $this->normalizeDetail($result->promotion),
            'packagesCreated' => count($result->packages),
            'packages' => array_map(fn ($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'destinationAmount' => $p->getDestinationAmount(),
                'destinationCurrency' => $p->getDestinationCurrency(),
            ], $result->packages),
            'tenantContractsLinked' => $result->tenantContractsLinked,
            'equivalences' => $this->serializeEquivalenceResult($result->equivalences),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/v2/equivalences', name: 'dashboard_promotions_v2_equivalences', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Cobertura de equivalencias por proveedor de una promoción V2', tag: 'Promotions')]
    public function equivalences(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeEquivalenceResult($this->equivalenceService->coverage($promotion)));
    }

    #[Route('/{id}/v2/equivalences/refresh', name: 'dashboard_promotions_v2_equivalences_refresh', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Vuelve a poblar las equivalencias por proveedor de una promoción V2', tag: 'Promotions')]
    public function refreshEquivalences(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeEquivalenceResult($this->equivalenceService->refreshForPromotion($promotion)));
    }

    #[Route('/{id}/v2/tenant-contracts/link', name: 'dashboard_promotions_v2_tenant_contracts_link', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Vincula los contratos propios de clientes existentes a los paquetes de una promoción V2', tag: 'Promotions')]
    public function linkTenantContracts(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $linked = $this->contractService->linkTenantContractsForPromotion($promotion);

        return $this->json(['tenantContractsLinked' => $linked]);
    }

    #[Route('/{id}', name: 'dashboard_promotions_update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Actualizar promoción', tag: 'Promotions', requestDto: UpdatePromotionDto::class)]
    public function update(int $id, UpdatePromotionDto $dto): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->promotionService->update($promotion, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->normalizeDetail($promotion));
    }

    #[Route('/{id}', name: 'dashboard_promotions_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Eliminar promoción', tag: 'Promotions', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($promotion);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    #[Route('/{id}/bindings', name: 'dashboard_promotions_bindings_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Vínculos explícitos promoción→producto por proveedor', tag: 'Promotions')]
    public function listBindings(int $id): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        $rows = $this->bindingService->listBindings($promotion, $promotion->getEnvironment());

        return $this->json(array_map(fn (array $row) => [
            'provider' => $row['provider'],
            'boundProduct' => $row['boundProduct'] !== null ? $this->serializeProduct($row['boundProduct']) : null,
            'candidates' => array_map(fn (CommunicationProduct $p) => $this->serializeProduct($p), $row['candidates']),
        ], $rows));
    }

    #[Route('/{id}/bindings/{provider}', name: 'dashboard_promotions_bindings_set', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Fijar el producto vinculado a una promoción para un proveedor', tag: 'Promotions', requestDto: SetPromotionProviderProductDto::class)]
    public function setBinding(int $id, string $provider, SetPromotionProviderProductDto $dto): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $binding = $this->bindingService->setBinding($promotion, $provider, (int) $dto->getProductId());
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'provider' => $binding->getProvider(),
            'boundProduct' => $this->serializeProduct($binding->getProduct()),
        ]);
    }

    #[Route('/{id}/bindings/{provider}', name: 'dashboard_promotions_bindings_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[DashboardEndpoint(summary: 'Quitar el vínculo explícito de una promoción para un proveedor', tag: 'Promotions', responseDto: DeletedOutDto::class)]
    public function deleteBinding(int $id, string $provider): JsonResponse
    {
        $promotion = $this->repository->find($id);
        if ($promotion === null) {
            return $this->json(['error' => ['message' => 'Promotion not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bindingService->removeBinding($promotion, $provider);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['deleted' => true]);
    }

    private function serializeEquivalenceResult(PromotionEquivalenceResult $result): array
    {
        return [
            'providers' => $result->providers,
            'gaps' => $result->gaps,
        ];
    }

    private function serializeProduct(?CommunicationProduct $product): ?array
    {
        if ($product === null) {
            return null;
        }

        return [
            'provider' => $product->getProvider(),
            'productId' => $product->getId(),
            'externalRef' => $product->getExternalRef(),
            'description' => $product->getDescription(),
            'wholesalePrice' => $product->getPrice() ?? 0.0,
            'priceCurrency' => $product->getPriceCurrency(),
        ];
    }

    private function normalizeDetail(CommunicationPromotions $promotion): array
    {
        return $this->serializer->normalize(
            $promotion,
            'json',
            [
                'groups' => ['promotion:detail'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]
        );
    }
}
