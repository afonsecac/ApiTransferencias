<?php

namespace App\Controller;

use App\DTO\CreateCommunicationContractBatchDto;
use App\DTO\CreateCommunicationContractDto;
use App\DTO\CreateCommunicationContractRangeDto;
use App\DTO\Out\BulkDeletedOutDto;
use App\DTO\Out\CommunicationContractOutDto;
use App\DTO\Out\ContractBatchResultOutDto;
use App\DTO\Out\ContractRangeResultOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\UpdateCommunicationContractDto;
use App\Entity\CommunicationContract;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\Pricing\CommunicationContractService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administración de CommunicationContract (V2) — visibilidad/precio por
 * cliente de un CommunicationPackage. `tenant IS NULL` = contrato "por
 * defecto"; ver PackageCatalogResolver para cómo se consume.
 */
#[IsGranted('ROLE_ADMIN')]
class DashboardCommunicationContractsController extends AbstractController
{
    private const SORTABLE = [
        'id' => 'c.id',
        'price' => 'c.price',
        'startAt' => 'c.startAt',
        'endAt' => 'c.endAt',
        'createdAt' => 'c.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationContractService $contractService,
    ) {
    }

    #[Route('/catalog/contracts', name: 'dashboard_communication_contracts_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar contratos de catálogo', tag: 'Communication Contracts', responseDto: PaginatedListOutDto::class, itemDto: CommunicationContractOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        // Sin fetch-join de `packages` aquí a propósito: es una colección
        // ManyToMany (to-many) y Doctrine no puede aplicar setMaxResults()
        // de forma fiable sobre una fetch-join de colección — cada paquete
        // extra de un contrato agrega una fila SQL, corriendo la paginación
        // (un contrato con 2 paquetes consume 2 cupos de $limit+1 en vez de
        // 1). `p` se usa solo para el filtro communicationPackageId; las
        // colecciones de la página se recargan aparte más abajo.
        $qb = $this->em->getRepository(CommunicationContract::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.tenant', 't')
            ->leftJoin('t.client', 'cl')
            ->addSelect('t')
            ->addSelect('cl');

        $this->applyFilters($qb, $request);

        $orderParts = explode(' ', $orderBy);
        $field = self::SORTABLE[$orderParts[0]] ?? 'c.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        $this->eagerLoadPackages($results);

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => array_map(fn (CommunicationContract $c) => $this->serialize($c), $results),
        ]);
    }

    #[Route('/catalog/contracts/{id}', name: 'dashboard_communication_contracts_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener contrato de catálogo', tag: 'Communication Contracts', responseDto: CommunicationContractOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $contract = $this->em->getRepository(CommunicationContract::class)->find($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($contract));
    }

    #[Route('/catalog/contracts', name: 'dashboard_communication_contracts_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear contrato de catálogo (un cliente, o por defecto)', tag: 'Communication Contracts', requestDto: CreateCommunicationContractDto::class, responseDto: CommunicationContractOutDto::class, responseStatusCode: 201)]
    public function create(CreateCommunicationContractDto $dto): JsonResponse
    {
        try {
            $contract = $this->contractService->createSingle($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($contract), Response::HTTP_CREATED);
    }

    #[Route('/catalog/contracts/batch', name: 'dashboard_communication_contracts_create_batch', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear contratos de catálogo en lote (mismo precio, varios clientes)', tag: 'Communication Contracts', requestDto: CreateCommunicationContractBatchDto::class, responseDto: ContractBatchResultOutDto::class, responseStatusCode: 201)]
    public function createBatch(CreateCommunicationContractBatchDto $dto): JsonResponse
    {
        try {
            $result = $this->contractService->createBatch($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'created' => $result->created,
            'updated' => $result->updated,
            'accountIds' => $result->accountIds,
        ], Response::HTTP_CREATED);
    }

    #[Route('/catalog/contracts/range', name: 'dashboard_communication_contracts_create_range', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear contratos de catálogo por rango de montos (precio interpolado entre extremos)', tag: 'Communication Contracts', requestDto: CreateCommunicationContractRangeDto::class, responseDto: ContractRangeResultOutDto::class, responseStatusCode: 201)]
    public function createByRange(CreateCommunicationContractRangeDto $dto): JsonResponse
    {
        try {
            $result = $this->contractService->createByRange($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json([
            'created' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'contractIds' => $result->contractIds,
            'skippedAmounts' => $result->skippedAmounts,
        ], Response::HTTP_CREATED);
    }

    #[Route('/catalog/contracts/{id}', name: 'dashboard_communication_contracts_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar contrato de catálogo', tag: 'Communication Contracts', requestDto: UpdateCommunicationContractDto::class, responseDto: CommunicationContractOutDto::class)]
    public function update(int $id, UpdateCommunicationContractDto $dto): JsonResponse
    {
        $contract = $this->em->getRepository(CommunicationContract::class)->find($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $contract = $this->contractService->update($contract, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($contract));
    }

    #[Route('/catalog/contracts/{id}/close', name: 'dashboard_communication_contracts_close', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Pausar (cerrar) contrato de catálogo', tag: 'Communication Contracts', responseDto: CommunicationContractOutDto::class)]
    public function close(int $id): JsonResponse
    {
        $contract = $this->em->getRepository(CommunicationContract::class)->find($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        $this->contractService->close($contract);

        return $this->json($this->serialize($contract));
    }

    #[Route('/catalog/contracts/{id}', name: 'dashboard_communication_contracts_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar contrato de catálogo', tag: 'Communication Contracts', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $contract = $this->em->getRepository(CommunicationContract::class)->find($id);
        if ($contract === null) {
            return $this->json(['error' => ['message' => 'Contract not found']], Response::HTTP_NOT_FOUND);
        }

        $this->contractService->delete($contract);

        return $this->json(['deleted' => true]);
    }

    /**
     * Borrado en lote — sin `tenantId`: TODOS los contratos del catálogo.
     * Con `tenantId` (Client.id): solo los de ese cliente, resuelto a su
     * cuenta ACTIVA en `environmentId` (obligatorio junto con `tenantId`,
     * mismo criterio que el alta). Pensado para no tener que borrar
     * contratos uno a uno desde el dashboard.
     */
    #[Route('/catalog/contracts', name: 'dashboard_communication_contracts_delete_bulk', methods: ['DELETE'])]
    #[DashboardEndpoint(summary: 'Eliminar todos los contratos, o los de un cliente', tag: 'Communication Contracts', responseDto: BulkDeletedOutDto::class)]
    public function deleteBulk(Request $request): JsonResponse
    {
        $tenantId = $request->query->get('tenantId') !== null ? (int) $request->query->get('tenantId') : null;
        $environmentId = $request->query->get('environmentId') !== null ? (int) $request->query->get('environmentId') : null;

        try {
            $deleted = $this->contractService->deleteAll($tenantId, $environmentId);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['deleted' => $deleted]);
    }

    /**
     * Recarga en lote las colecciones `packages` de los contratos de la
     * página actual — a salvo del fan-out de la paginación (ver comentario
     * en list()) porque acá no hay setMaxResults(): el IN (:ids) ya acota
     * el resultado a exactamente los contratos de esta página.
     *
     * @param list<CommunicationContract> $contracts
     */
    private function eagerLoadPackages(array $contracts): void
    {
        $ids = array_map(static fn (CommunicationContract $c) => $c->getId(), $contracts);
        if ($ids === []) {
            return;
        }

        $this->em->getRepository(CommunicationContract::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.packages', 'p')
            ->addSelect('p')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()->getResult();
    }

    private function applyFilters(QueryBuilder $qb, Request $request): void
    {
        if ($packageId = $request->query->get('communicationPackageId')) {
            $qb->andWhere(':packageId MEMBER OF c.packages')->setParameter('packageId', $packageId);
        }
        if ($tenantId = $request->query->get('tenantId')) {
            // tenantId es Client.id (mismo espacio que en los DTOs de alta,
            // ver CommunicationContractService::resolveTenantOrFail) — NO
            // Account.id. `t` es la cuenta (tenant); `cl` es su cliente.
            $qb->andWhere('cl.id = :tenantId')->setParameter('tenantId', $tenantId);
        }
        if ($request->query->getBoolean('defaultsOnly')) {
            $qb->andWhere('c.tenant IS NULL');
        }
        if ($request->query->getBoolean('openOnly')) {
            $qb->andWhere('c.endAt IS NULL');
        }
    }

    private function serialize(CommunicationContract $contract): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id' => $contract->getId(),
            'packages' => array_map(
                static fn ($p) => ['id' => $p->getId(), 'name' => $p->getName()],
                $contract->getPackages()->toArray(),
            ),
            'tenant' => [
                'id' => $contract->getTenant()?->getId(),
                'clientName' => $contract->getTenant()?->getClient()?->getCompanyName(),
            ],
            'destinationAmount' => $contract->getDestinationAmount(),
            'destinationCurrency' => $contract->getDestinationCurrency(),
            'price' => $contract->getPrice(),
            'currency' => $contract->getCurrency(),
            'startAt' => $contract->getStartAt()?->format('c'),
            'endAt' => $contract->getEndAt()?->format('c'),
            'isActive' => $contract->isActiveAt($now),
            'createdAt' => $contract->getCreatedAt()?->format('c'),
            'updatedAt' => $contract->getUpdatedAt()?->format('c'),
            'createdByEmail' => $contract->getCreatedBy()?->getEmail(),
        ];
    }
}
