<?php

namespace App\Controller;

use App\DTO\CreateSysConfigDto;
use App\DTO\UpdateSysConfigDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\SysConfigOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\SysConfig;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Repository\SysConfigRepository;
use App\Service\SysConfigAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/sys-config')]
class DashboardSysConfigController extends AbstractController
{
    private const SORTABLE = [
        'id'           => 'sc.id',
        'propertyName' => 'sc.propertyName',
        'updatedAt'    => 'sc.updatedAt',
        'createdAt'    => 'sc.createdAt',
    ];

    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly SysConfigAdminService $sysConfigAdminService,
    ) {}

    #[Route('', name: 'dashboard_sys_config_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar variables de configuración del sistema', tag: 'Sys Config', responseDto: PaginatedListOutDto::class, itemDto: SysConfigOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page    = max(0, (int) $request->query->get('page', 0));
        $limit   = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');
        $search  = $request->query->get('search');

        $qb = $this->sysConfigRepo->createQueryBuilder('sc')
            ->andWhere('sc.removedAt IS NULL');

        if ($search !== null && $search !== '') {
            $qb->andWhere('sc.propertyName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($request->query->has('isActive')) {
            $qb->andWhere('sc.isActive = :isActive')
               ->setParameter('isActive', filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN));
        }

        $orderParts = explode(' ', $orderBy);
        $field      = self::SORTABLE[$orderParts[0]] ?? 'sc.id';
        $direction  = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)
            ->setFirstResult($page * $limit)
            ->getQuery()
            ->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit'       => $limit,
            'currentPage' => $page,
            'hasNext'     => $hasNext,
            'hasPrevious' => $page > 0,
            'results'     => array_map(fn($sc) => $this->serialize($sc), $results),
        ]);
    }

    #[Route('/{id}', name: 'dashboard_sys_config_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener variable de configuración', tag: 'Sys Config', responseDto: SysConfigOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $config = $this->findOrFail($id);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return $this->json($this->serialize($config));
    }

    #[Route('', name: 'dashboard_sys_config_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear variable de configuración', tag: 'Sys Config', responseDto: SysConfigOutDto::class, responseStatusCode: 201)]
    public function create(CreateSysConfigDto $dto): JsonResponse
    {
        try {
            $config = $this->sysConfigAdminService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($config), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'dashboard_sys_config_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar variable de configuración', tag: 'Sys Config', responseDto: SysConfigOutDto::class)]
    public function update(int $id, UpdateSysConfigDto $dto): JsonResponse
    {
        $config = $this->findOrFail($id);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $config = $this->sysConfigAdminService->update($config, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($config));
    }

    #[Route('/{id}/toggle', name: 'dashboard_sys_config_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar variable de configuración', tag: 'Sys Config', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $config = $this->findOrFail($id);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $this->sysConfigAdminService->toggle($config);

        return $this->json(['id' => $config->getId(), 'isActive' => $config->isActive()]);
    }

    #[Route('/{id}', name: 'dashboard_sys_config_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar variable de configuración (soft-delete)', tag: 'Sys Config', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $config = $this->findOrFail($id);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $this->sysConfigAdminService->delete($config);

        return $this->json(['deleted' => true]);
    }

    private function findOrFail(int $id): SysConfig|JsonResponse
    {
        $config = $this->sysConfigRepo->find($id);
        if ($config === null || $config->getRemovedAt() !== null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        return $config;
    }

    private function serialize(SysConfig $config): array
    {
        return [
            'id'            => $config->getId(),
            'propertyName'  => $config->getPropertyName(),
            'propertyValue' => $config->getPropertyValue(),
            'isActive'      => $config->isActive(),
            'clients'       => $config->getClients(),
            'createdAt'     => $config->getCreatedAt()?->format('c'),
            'updatedAt'     => $config->getUpdatedAt()?->format('c'),
            'removedAt'     => $config->getRemovedAt()?->format('c'),
        ];
    }
}
