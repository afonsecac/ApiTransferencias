<?php

namespace App\Controller;

use App\DTO\CreateFixedContractDto;
use App\DTO\CreateRateContractDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PackageContractOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ResolvedPriceOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\UpdatePricePackageDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationPackageService;
use App\Service\Pricing\PackageSalePriceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administración de contratos de precio por cliente — pantalla propia
 * separada de /client/prices (que sigue siendo el CRUD crudo de
 * CommunicationPricePackage, sin el concepto de contrato).
 *
 * Fase 3 de la deprecación de V1: las altas nuevas (createFixed/createByRate,
 * antes delegadas en PackageContractService) están deshabilitadas — devuelven
 * 410 y redirigen al alta V2 (DashboardCommunicationContractsController,
 * /catalog/contracts). Editar/activar/desactivar/borrar contratos V1
 * existentes sigue funcionando: no es un alta nueva, y la promoción #90
 * activa en producción hasta 2026-09-01 depende de datos V1 vivos.
 */
#[IsGranted('ROLE_ADMIN')]
class DashboardPackageContractsController extends AbstractController
{
    private const SORTABLE = [
        'id' => 'pp.id',
        'amount' => 'pp.amount',
        'name' => 'pp.name',
        'isActive' => 'pp.isActive',
        'createdAt' => 'pp.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationPackageService $packageService,
        private readonly PackageSalePriceResolver $salePriceResolver,
    ) {
    }

    #[Route('/client/contracts', name: 'dashboard_package_contracts_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar contratos de precio', tag: 'Package Contracts', responseDto: PaginatedListOutDto::class, itemDto: PackageContractOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationPricePackage::class)
            ->createQueryBuilder('pp')
            ->leftJoin('pp.tenant', 't')
            ->leftJoin('t.client', 'c')
            ->leftJoin('pp.referencePackage', 'rp')
            ->leftJoin('rp.environment', 'e')
            ->addSelect('t')
            ->addSelect('c')
            ->where('pp.isContract = :isContract')
            ->setParameter('isContract', true);

        $this->applyFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::SORTABLE[$orderParts[0]] ?? 'pp.id';
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
            'results' => array_map(fn (CommunicationPricePackage $pp) => $this->serializeContract($pp), $results),
        ]);
    }

    #[Route('/client/contracts/preview', name: 'dashboard_package_contracts_preview', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Previsualizar el precio resuelto de un paquete para un cliente', tag: 'Package Contracts', responseDto: ResolvedPriceOutDto::class)]
    public function preview(Request $request): JsonResponse
    {
        $packageId = (int) $request->query->get('packageId', 0);
        $tenantId = (int) $request->query->get('tenantId', 0);

        $package = $this->em->getRepository(CommunicationClientPackage::class)->find($packageId);
        if ($package === null) {
            return $this->json(['error' => ['message' => 'Package not found']], Response::HTTP_NOT_FOUND);
        }

        $tenant = $this->em->getRepository(Account::class)->find($tenantId);
        if ($tenant === null) {
            return $this->json(['error' => ['message' => 'Tenant not found']], Response::HTTP_NOT_FOUND);
        }

        $resolved = $this->salePriceResolver->resolve($package, $tenant);

        return $this->json([
            'amount' => $resolved->amount,
            'currency' => $resolved->currency,
            'source' => $resolved->source->value,
            'contractId' => $resolved->contractId,
            'note' => $resolved->note,
        ]);
    }

    #[Route('/client/contracts/{id}', name: 'dashboard_package_contracts_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener contrato de precio', tag: 'Package Contracts', responseDto: PackageContractOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $contract = $this->findContractOrNull($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeContract($contract));
    }

    #[Route('/client/contracts/fixed', name: 'dashboard_package_contracts_create_fixed', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear contrato de precio (V1, deshabilitado — usar /catalog/contracts)', tag: 'Package Contracts', requestDto: CreateFixedContractDto::class, responseStatusCode: 201)]
    public function createFixed(CreateFixedContractDto $dto): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => 'El alta de contratos de precio V1 está deshabilitada — usá el alta V2 (POST /catalog/contracts).', 'code' => 'V1_CONTRACT_CREATION_DISABLED']],
            Response::HTTP_GONE,
        );
    }

    #[Route('/client/contracts/rate', name: 'dashboard_package_contracts_create_rate', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear contratos de precio por tasa (V1, deshabilitado — usar /catalog/contracts/range)', tag: 'Package Contracts', requestDto: CreateRateContractDto::class, responseStatusCode: 201)]
    public function createByRate(CreateRateContractDto $dto): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => 'El alta de contratos de precio V1 está deshabilitada — usá el alta V2 (POST /catalog/contracts/range).', 'code' => 'V1_CONTRACT_CREATION_DISABLED']],
            Response::HTTP_GONE,
        );
    }

    #[Route('/client/contracts/{id}', name: 'dashboard_package_contracts_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar contrato de precio', tag: 'Package Contracts', requestDto: UpdatePricePackageDto::class, responseDto: PackageContractOutDto::class)]
    public function update(int $id, UpdatePricePackageDto $dto): JsonResponse
    {
        $contract = $this->findContractOrNull($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        $contract = $this->packageService->updatePrice($contract, $dto);

        return $this->json($this->serializeContract($contract));
    }

    #[Route('/client/contracts/{id}/toggle', name: 'dashboard_package_contracts_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar contrato de precio', tag: 'Package Contracts', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $contract = $this->findContractOrNull($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->togglePrice($contract);

        return $this->json(['id' => $contract->getId(), 'isActive' => $contract->isActive()]);
    }

    #[Route('/client/contracts/{id}', name: 'dashboard_package_contracts_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar contrato de precio', tag: 'Package Contracts', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $contract = $this->findContractOrNull($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        $this->packageService->deletePrice($contract);

        return $this->json(['deleted' => true]);
    }

    private function findContractOrNull(int $id): ?CommunicationPricePackage
    {
        $pp = $this->em->getRepository(CommunicationPricePackage::class)->find($id);

        return ($pp !== null && $pp->isContract()) ? $pp : null;
    }

    private function applyFilters(QueryBuilder $qb, Request $request): void
    {
        if ($clientId = $request->query->get('clientId')) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
        }
        if ($referencePackageId = $request->query->get('referencePackageId')) {
            $qb->andWhere('rp.id = :referencePackageId')->setParameter('referencePackageId', $referencePackageId);
        }
        if ($mode = $request->query->get('mode')) {
            $qb->andWhere('pp.contractMode = :mode')->setParameter('mode', $mode);
        }
        if ($envType = $request->query->get('environmentType')) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }
        if ($search = $request->query->get('search')) {
            $qb->andWhere('pp.name LIKE :s')->setParameter('s', "%{$search}%");
        }
    }

    private function serializeContract(CommunicationPricePackage $pp): array
    {
        return [
            'id' => $pp->getId(),
            'name' => $pp->getName(),
            'description' => $pp->getDescription(),
            'contractMode' => $pp->getContractMode(),
            'basePrice' => $pp->getBasePrice(),
            'baseCurrency' => $pp->getBaseCurrency(),
            'rateValue' => $pp->getRateValue(),
            'amount' => $pp->getAmount(),
            'currency' => $pp->getCurrency(),
            'isActive' => $pp->isActive(),
            'activeStartAt' => $pp->getActiveStartAt()?->format('c'),
            'activeEndAt' => $pp->getActiveEndAt()?->format('c'),
            'tenant' => [
                'id' => $pp->getTenant()?->getId(),
                'clientName' => $pp->getTenant()?->getClient()?->getCompanyName(),
            ],
            'referencePackageId' => $pp->getReferencePackage()?->getId(),
        ];
    }
}
