<?php

namespace App\Controller;

use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreateCommunicationPackageDto;
use App\DTO\Out\CommunicationPackageOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PackageBatchResultOutDto;
use App\DTO\Out\PackageCoverageItemOutDto;
use App\DTO\Out\PackageProviderBindingOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ResolvedPriceOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\SetPackageProviderProductDto;
use App\DTO\UpdateCommunicationPackageDto;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPackageBindingService;
use App\Service\Pricing\PackageCatalogResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administración del catálogo agnóstico de proveedor (V2) —
 * App\Entity\CommunicationPackage: único y global, sin tenant propio; la
 * visibilidad/precio por cliente se decide vía /catalog/contracts y
 * PackageCatalogResolver.
 */
#[IsGranted('ROLE_ADMIN')]
class DashboardCommunicationPackagesController extends AbstractController
{
    private const SORTABLE = [
        'id' => 'p.id',
        'name' => 'p.name',
        'destinationAmount' => 'p.destinationAmount',
        'isActive' => 'p.isActive',
        'displayOrder' => 'p.displayOrder',
        'createdAt' => 'p.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationPackageAdminService $packageService,
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageBindingService $bindingService,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
    ) {
    }

    #[Route('/catalog/packages', name: 'dashboard_communication_packages_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar paquetes del catálogo', tag: 'Communication Packages', responseDto: PaginatedListOutDto::class, itemDto: CommunicationPackageOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'displayOrder ASC');

        $qb = $this->em->getRepository(CommunicationPackage::class)->createQueryBuilder('p');
        $this->applyFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::SORTABLE[$orderParts[0]] ?? 'p.displayOrder';
        $direction = strtoupper($orderParts[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $qb->orderBy($field, $direction);
        if ($field !== 'p.id') {
            $qb->addOrderBy('p.id', 'ASC');
        }

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        $boundIds = $this->bindingRepo->findPackageIdsWithBindings(
            array_map(static fn (CommunicationPackage $p) => (int) $p->getId(), $results)
        );

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn (CommunicationPackage $p) => $this->serialize($p, in_array($p->getId(), $boundIds, true)), $results),
        ]);
    }

    #[Route('/catalog/packages/preview', name: 'dashboard_communication_packages_preview', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Previsualizar el precio/visibilidad resuelta de un paquete para un cliente', tag: 'Communication Packages', responseDto: ResolvedPriceOutDto::class)]
    public function preview(Request $request): JsonResponse
    {
        $packageId = (int) $request->query->get('packageId', 0);
        $tenantId = (int) $request->query->get('tenantId', 0);

        $package = $this->em->getRepository(CommunicationPackage::class)->find($packageId);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $tenant = $this->em->getRepository(Account::class)->find($tenantId);
        if ($tenant === null) {
            return $this->json(['error' => ['message' => 'Tenant not found']], Response::HTTP_NOT_FOUND);
        }

        $offer = $this->catalogResolver->offerFor($package, $tenant);
        if ($offer === null) {
            return $this->json([
                'amount' => 0.0,
                'currency' => $package->getDestinationCurrency(),
                'source' => 'NOT_VISIBLE',
                'contractId' => null,
                'note' => 'Este cliente tiene contrato(s) pero ninguno cubre este paquete.',
            ]);
        }

        return $this->json([
            'amount' => $offer->price,
            'currency' => $offer->currency,
            'source' => $offer->source->value,
            'contractId' => $offer->contractId,
            'note' => $offer->note,
        ]);
    }

    #[Route('/catalog/packages/{id}/coverage', name: 'dashboard_communication_packages_coverage', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Qué proveedores cubren la tupla de destino del paquete, y a qué costo', tag: 'Communication Packages', responseDto: PackageCoverageItemOutDto::class, responseIsArray: true)]
    public function coverage(int $id, Request $request): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $environmentId = $request->query->get('environmentId');
        $environment = $environmentId ? $this->em->getRepository(Environment::class)->find($environmentId) : null;

        return $this->json($this->packageService->coverage($package, $environment));
    }

    #[Route('/catalog/packages/{id}/bindings', name: 'dashboard_communication_packages_bindings_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Vínculos explícitos paquete→producto por proveedor', tag: 'Communication Packages', responseDto: PackageProviderBindingOutDto::class, responseIsArray: true)]
    public function listBindings(int $id, Request $request): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $environmentId = $request->query->get('environmentId');
        $environment = $environmentId ? $this->em->getRepository(Environment::class)->find($environmentId) : null;

        $rows = $this->bindingService->listBindings($package, $environment);

        return $this->json(array_map(fn (array $row) => [
            'provider' => $row['provider'],
            'boundProduct' => $row['boundProduct'] !== null ? $this->serializeProduct($row['boundProduct']) : null,
            'candidates' => array_map(fn (CommunicationProduct $p) => $this->serializeProduct($p), $row['candidates']),
            'autoMatched' => $row['autoMatched'],
        ], $rows));
    }

    #[Route('/catalog/packages/{id}/bindings/{provider}', name: 'dashboard_communication_packages_bindings_set', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Fijar el producto vinculado a un paquete para un proveedor', tag: 'Communication Packages', requestDto: SetPackageProviderProductDto::class)]
    public function setBinding(int $id, string $provider, SetPackageProviderProductDto $dto): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $binding = $this->bindingService->setBinding($package, $provider, (int) $dto->getProductId());
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'provider' => $binding->getProvider(),
            'boundProduct' => $this->serializeProduct($binding->getProduct()),
        ]);
    }

    #[Route('/catalog/packages/{id}/bindings/{provider}', name: 'dashboard_communication_packages_bindings_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Quitar el vínculo explícito de un paquete para un proveedor', tag: 'Communication Packages', responseDto: DeletedOutDto::class)]
    public function deleteBinding(int $id, string $provider): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bindingService->removeBinding($package, $provider);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['deleted' => true]);
    }

    #[Route('/catalog/packages/{id}', name: 'dashboard_communication_packages_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener paquete del catálogo', tag: 'Communication Packages', responseDto: CommunicationPackageOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $hasBindings = $this->bindingRepo->findAllForPackage($package) !== [];

        return $this->json($this->serialize($package, $hasBindings));
    }

    #[Route('/catalog/packages', name: 'dashboard_communication_packages_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear paquete del catálogo', tag: 'Communication Packages', requestDto: CreateCommunicationPackageDto::class, responseDto: CommunicationPackageOutDto::class, responseStatusCode: 201)]
    public function create(CreateCommunicationPackageDto $dto): JsonResponse
    {
        try {
            $package = $this->packageService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($package), Response::HTTP_CREATED);
    }

    #[Route('/catalog/packages/batch', name: 'dashboard_communication_packages_create_batch', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear paquetes del catálogo en lote (rango de montos con salto)', tag: 'Communication Packages', requestDto: CreateCommunicationPackageBatchDto::class, responseDto: PackageBatchResultOutDto::class, responseStatusCode: 201)]
    public function createBatch(CreateCommunicationPackageBatchDto $dto): JsonResponse
    {
        try {
            $packages = $this->packageService->createBatch($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'created' => count($packages),
            'packageIds' => array_map(static fn (CommunicationPackage $p) => $p->getId(), $packages),
        ], Response::HTTP_CREATED);
    }

    #[Route('/catalog/packages/{id}', name: 'dashboard_communication_packages_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar paquete del catálogo', tag: 'Communication Packages', requestDto: UpdateCommunicationPackageDto::class, responseDto: CommunicationPackageOutDto::class)]
    public function update(int $id, UpdateCommunicationPackageDto $dto): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $package = $this->packageService->update($package, $dto);
        $hasBindings = $this->bindingRepo->findAllForPackage($package) !== [];

        return $this->json($this->serialize($package, $hasBindings));
    }

    #[Route('/catalog/packages/{id}/toggle', name: 'dashboard_communication_packages_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar paquete del catálogo', tag: 'Communication Packages', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->toggle($package);

        return $this->json(['id' => $package->getId(), 'isActive' => $package->isActive()]);
    }

    #[Route('/catalog/packages/{id}', name: 'dashboard_communication_packages_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar paquete del catálogo', tag: 'Communication Packages', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->packageService->delete($package);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['deleted' => true]);
    }

    private function applyFilters(QueryBuilder $qb, Request $request): void
    {
        if ($request->query->has('isActive')) {
            $qb->andWhere('p.isActive = :isActive')->setParameter('isActive', $request->query->getBoolean('isActive'));
        }
        if ($currency = $request->query->get('destinationCurrency')) {
            $qb->andWhere('p.destinationCurrency = :currency')->setParameter('currency', strtoupper($currency));
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('p.name LIKE :s')->setParameter('s', "%{$search}%");
        }
        if ($request->query->has('inPromotion')) {
            $qb->andWhere($request->query->getBoolean('inPromotion') ? 'p.promotion IS NOT NULL' : 'p.promotion IS NULL');
        }
        if ($tag = $request->query->get('tag')) {
            // tags es json (no jsonb) — DQL no puede comparar json con LIKE
            // directamente (Postgres lo rechaza: "operator does not exist:
            // json ~~ unknown"), así que se resuelve el conjunto de ids por
            // SQL crudo y se filtra por IN() en la QueryBuilder normal.
            $ids = $this->packageIdsWithTag($tag);
            $qb->andWhere('p.id IN (:tagIds)')->setParameter('tagIds', $ids === [] ? [0] : $ids);
        }
        if ($createdFrom = $request->query->get('createdFrom')) {
            $qb->andWhere('p.createdAt >= :createdFrom')->setParameter('createdFrom', new \DateTimeImmutable($createdFrom));
        }
        if ($createdTo = $request->query->get('createdTo')) {
            $qb->andWhere('p.createdAt <= :createdTo')->setParameter('createdTo', new \DateTimeImmutable($createdTo . ' 23:59:59'));
        }
    }

    /**
     * @return list<int>
     */
    private function packageIdsWithTag(string $tag): array
    {
        $rows = $this->em->getConnection()->executeQuery(
            'SELECT id FROM communication_package WHERE tags::text LIKE :tag',
            ['tag' => '%"' . addcslashes($tag, '%_\\') . '"%']
        )->fetchFirstColumn();

        return array_map('intval', $rows);
    }

    private function serialize(CommunicationPackage $package, bool $hasBindings = false): array
    {
        return [
            'id' => $package->getId(),
            'name' => $package->getName(),
            'description' => $package->getDescription(),
            'knowMore' => $package->getKnowMore(),
            'benefits' => $package->getBenefits(),
            'tags' => $package->getTags(),
            'service' => $package->getService(),
            'validity' => $package->getValidity(),
            'destination' => $package->getDestination(),
            'isActive' => $package->isActive(),
            'activeStartAt' => $package->getActiveStartAt()?->format('c'),
            'activeEndAt' => $package->getActiveEndAt()?->format('c'),
            'displayOrder' => $package->getDisplayOrder(),
            'createdAt' => $package->getCreatedAt()?->format('c'),
            'updatedAt' => $package->getUpdatedAt()?->format('c'),
            'hasBindings' => $hasBindings,
            'promotionId' => $package->getPromotion()?->getId(),
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
}
