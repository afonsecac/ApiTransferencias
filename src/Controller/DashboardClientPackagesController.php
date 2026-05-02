<?php

namespace App\Controller;

use App\DTO\CreateClientPackageDto;
use App\DTO\CreatePricePackageDto;
use App\DTO\UpdateClientPackageDto;
use App\DTO\UpdatePricePackageDto;
use App\DTO\Out\ClientPackageDetailOutDto;
use App\DTO\Out\ClientPackageOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\PricePackageOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationPackageService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
class DashboardClientPackagesController extends AbstractController
{
    private const PRICE_SORTABLE = [
        'id' => 'pp.id',
        'price' => 'pp.price',
        'amount' => 'pp.amount',
        'name' => 'pp.name',
        'isActive' => 'pp.isActive',
    ];

    private const PACKAGE_SORTABLE = [
        'id' => 'cp.id',
        'name' => 'cp.name',
        'amount' => 'cp.amount',
        'activeStartAt' => 'cp.activeStartAt',
        'activeEndAt' => 'cp.activeEndAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly CommunicationPackageService $packageService,
    ) {
    }

    // ═══════════════════════════════════════════
    //  PRICE PACKAGES
    // ═══════════════════════════════════════════

    #[Route('/client/prices', name: 'dashboard_client_prices_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar price packages', tag: 'Client Prices', responseDto: PaginatedListOutDto::class, itemDto: PricePackageOutDto::class)]
    public function listPrices(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationPricePackage::class)
            ->createQueryBuilder('pp')
            ->leftJoin('pp.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('pp.environment', 'e')
            ->leftJoin('pp.product', 'pr');

        $this->applyPriceFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::PRICE_SORTABLE[$orderParts[0]] ?? 'pp.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn($pp) => $this->serializePricePackage($pp), $results),
        ]);
    }

    #[Route('/client/prices/catalogue', name: 'dashboard_client_prices_catalogue', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Catálogo de precios activos', tag: 'Client Prices')]
    public function listCommunicationPrices(): JsonResponse
    {
        $prices = $this->em->getRepository(CommunicationPrice::class)
            ->createQueryBuilder('cp')
            ->where('cp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('cp.startPrice', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map(fn($p) => [
            'id' => $p->getId(),
            'startPrice' => $p->getStartPrice(),
            'endPrice' => $p->getEndPrice(),
            'currencyPrice' => $p->getCurrencyPrice(),
            'amount' => $p->getAmount(),
            'currency' => $p->getCurrency(),
        ], $prices));
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener price package', tag: 'Client Prices', responseDto: PricePackageOutDto::class)]
    public function showPrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializePricePackage($pp));
    }

    #[Route('/client/prices', name: 'dashboard_client_prices_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear price package', tag: 'Client Prices', requestDto: CreatePricePackageDto::class, responseDto: PricePackageOutDto::class, responseStatusCode: 201)]
    public function createPrice(CreatePricePackageDto $dto): JsonResponse
    {
        try {
            $pp = $this->packageService->createPrice($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serializePricePackage($pp), Response::HTTP_CREATED);
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar price package', tag: 'Client Prices', requestDto: UpdatePricePackageDto::class, responseDto: PricePackageOutDto::class)]
    public function updatePrice(int $id, UpdatePricePackageDto $dto): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }

        $pp = $this->packageService->updatePrice($pp, $dto);

        return $this->json($this->serializePricePackage($pp));
    }

    #[Route('/client/prices/{id}/toggle', name: 'dashboard_client_prices_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar price package', tag: 'Client Prices', responseDto: ToggleOutDto::class)]
    public function togglePrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->togglePrice($pp);

        return $this->json(['id' => $pp->getId(), 'isActive' => $pp->isActive()]);
    }

    #[Route('/client/prices/{id}', name: 'dashboard_client_prices_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar price package', tag: 'Client Prices', responseDto: DeletedOutDto::class)]
    public function deletePrice(int $id): JsonResponse
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);
        if ($pp === null) {
            return $this->json(['error' => ['message' => 'Price package not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->deletePrice($pp);

        return $this->json(['deleted' => true]);
    }

    // ═══════════════════════════════════════════
    //  CLIENT PACKAGES
    // ═══════════════════════════════════════════

    #[Route('/client/packages', name: 'dashboard_client_packages_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear client package', tag: 'Client Packages', requestDto: CreateClientPackageDto::class, responseDto: ClientPackageDetailOutDto::class, responseStatusCode: 201)]
    public function createPackage(CreateClientPackageDto $dto): JsonResponse
    {
        try {
            $cp = $this->packageService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serializeClientPackageDetail($cp), Response::HTTP_CREATED);
    }

    #[Route('/client/packages', name: 'dashboard_client_packages_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar client packages', tag: 'Client Packages', responseDto: PaginatedListOutDto::class, itemDto: ClientPackageOutDto::class)]
    public function listPackages(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationClientPackage::class)
            ->createQueryBuilder('cp')
            ->leftJoin('cp.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e');

        $this->applyPackageFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::PACKAGE_SORTABLE[$orderParts[0]] ?? 'cp.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn($cp) => $this->serializeClientPackage($cp), $results),
        ]);
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener client package', tag: 'Client Packages', responseDto: ClientPackageDetailOutDto::class)]
    public function showPackage(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializeClientPackageDetail($cp));
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar client package', tag: 'Client Packages', requestDto: UpdateClientPackageDto::class, responseDto: ClientPackageDetailOutDto::class)]
    public function updatePackage(int $id, UpdateClientPackageDto $dto): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $cp = $this->packageService->updateClientPackage($cp, $dto);

        return $this->json($this->serializeClientPackageDetail($cp));
    }

    #[Route('/client/packages/{id}', name: 'dashboard_client_packages_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar client package', tag: 'Client Packages', responseDto: DeletedOutDto::class)]
    public function deletePackage(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationClientPackage::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->deleteClientPackage($cp);

        return $this->json(['deleted' => true]);
    }

    // ═══════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════

    private function applyPriceFilters(QueryBuilder $qb, Request $request): void
    {
        $this->applyClientFilter($qb, 'c', $request);
        $this->applyActiveFilter($qb, 'pp');

        if ($envType = $request->query->get('environmentType')) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }
        if ($productId = $request->query->get('productId')) {
            $qb->andWhere('pr.id = :productId')->setParameter('productId', $productId);
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('pp.name LIKE :s')->setParameter('s', "%{$search}%");
        }
    }

    private function applyPackageFilters(QueryBuilder $qb, Request $request): void
    {
        $this->applyClientFilter($qb, 'c', $request);
        $this->applyActivePackageFilter($qb, 'cp');

        if ($envType = $request->query->get('environmentType')) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }
        if ($tenantId = $request->query->get('tenantId')) {
            $qb->andWhere('a.id = :tenantId')->setParameter('tenantId', $tenantId);
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('cp.name LIKE :s')->setParameter('s', "%{$search}%");
        }
    }

    private function applyClientFilter(QueryBuilder $qb, string $clientAlias, Request $request): void
    {
        $clientId = $request->query->get('clientId');

        /** @var User|null $user */
        $user = $this->security->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($clientId) {
                $qb->andWhere("{$clientAlias}.id = :clientId")
                    ->setParameter('clientId', $clientId);
            }
        } elseif ($user instanceof User && $user->getCompany() !== null) {
            $qb->andWhere("{$clientAlias}.id = :authClientId")
                ->setParameter('authClientId', $user->getCompany()->getId());
        }
    }

    private function applyActiveFilter(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere("{$alias}.isActive = :isActive")->setParameter('isActive', true);
        $qb->andWhere("({$alias}.activeEndAt IS NULL OR {$alias}.activeEndAt >= :nowEnd)")
            ->setParameter('nowEnd', new \DateTimeImmutable());
    }

    private function applyActivePackageFilter(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere("({$alias}.activeEndAt IS NULL OR {$alias}.activeEndAt >= :nowEnd)")
            ->setParameter('nowEnd', new \DateTimeImmutable());
    }

    private function serializePricePackage(CommunicationPricePackage $pp): array
    {
        return [
            'id' => $pp->getId(),
            'name' => $pp->getName(),
            'description' => $pp->getDescription(),
            'price' => $pp->getPrice(),
            'priceCurrency' => $pp->getPriceCurrency(),
            'amount' => $pp->getAmount(),
            'currency' => $pp->getCurrency(),
            'isActive' => $pp->isActive(),
            'activeStartAt' => $pp->getActiveStartAt()?->format('c'),
            'activeEndAt' => $pp->getActiveEndAt()?->format('c'),
            'tenant' => [
                'id' => $pp->getTenant()?->getId(),
                'clientName' => $pp->getTenant()?->getClient()?->getCompanyName(),
            ],
            'environment' => $pp->getEnvironment() ? [
                'id' => $pp->getEnvironment()->getId(),
                'type' => $pp->getEnvironment()->getType(),
            ] : null,
            'product' => $pp->getProduct() ? [
                'id' => $pp->getProduct()->getId(),
                'description' => $pp->getProduct()->getDescription(),
            ] : null,
        ];
    }

    private function serializeClientPackage(CommunicationClientPackage $cp): array
    {
        return [
            'id' => $cp->getId(),
            'name' => $cp->getName(),
            'description' => $cp->getDescription(),
            'amount' => $cp->getAmount(),
            'currency' => $cp->getCurrency(),
            'activeStartAt' => $cp->getActiveStartAt()?->format('c'),
            'activeEndAt' => $cp->getActiveEndAt()?->format('c'),
            'tenant' => [
                'id' => $cp->getTenant()?->getId(),
                'clientName' => $cp->getTenant()?->getClient()?->getCompanyName(),
            ],
        ];
    }

    private function serializeClientPackageDetail(CommunicationClientPackage $cp): array
    {
        $data = $this->serializeClientPackage($cp);
        $data['benefits'] = $cp->getBenefits();
        $data['tags'] = $cp->getTags();
        $data['service'] = $cp->getService();
        $data['destination'] = $cp->getDestination();
        $data['validity'] = $cp->getValidity();
        $data['knowMore'] = $cp->getKnowMore();
        $data['pricePackage'] = $cp->getPriceClientPackage() ? $this->serializePricePackage($cp->getPriceClientPackage()) : null;
        return $data;
    }
}
