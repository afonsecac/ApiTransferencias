<?php

namespace App\Controller;

use App\DTO\CreateProviderRoutingDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ProviderRoutingOutDto;
use App\DTO\Out\ProviderRoutingPreviewOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\ClientProviderRouting;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use App\Service\ProviderRoutingAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard de administración del enrutado de proveedor por cliente (Fase 2).
 * Lectura: ROLE_ADMIN. Escritura: ROLE_SUPER_ADMIN — cambiar el proveedor de
 * un cliente mueve dinero real, mismo criterio que DashboardSysConfigController.
 *
 * El kill switch global (communications.provider.routing.enabled) NO tiene
 * endpoint propio: ya es una fila de sys_config editable desde
 * /admin/sys-config, igual que communications.dispatch.enabled.
 */
#[Route('/admin/providers')]
#[IsGranted('ROLE_ADMIN')]
class DashboardProviderRoutingController extends AbstractController
{
    private const SORTABLE = [
        'id'        => 'cpr.id',
        'clientId'  => 'cpr.client',
        'provider'  => 'cpr.provider',
        'updatedAt' => 'cpr.updatedAt',
        'createdAt' => 'cpr.createdAt',
    ];

    public function __construct(
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly ProviderRoutingAdminService $adminService,
        private readonly ProviderRegistry $providerRegistry,
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    #[Route('', name: 'dashboard_providers_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar proveedores registrados y estado del kill switch', tag: 'Provider Routing')]
    public function listProviders(): JsonResponse
    {
        $registered = $this->providerRegistry->registered();

        return $this->json([
            'providers' => array_map(fn (CommunicationProviderEnum $code) => [
                'code' => $code->value,
                'capabilities' => array_map(
                    fn ($c) => $c->value,
                    $this->providerRegistry->get($code)->getCapabilities(),
                ),
            ], $registered),
            'routingEnabled' => $this->sysConfigRepo->findCachedValue(ProviderResolver::ROUTING_ENABLED_KEY) !== '0',
            'defaultProvider' => $this->sysConfigRepo->findCachedValue(ProviderResolver::DEFAULT_PROVIDER_KEY) ?? CommunicationProviderEnum::ETECSA->value,
        ]);
    }

    #[Route('/routing', name: 'dashboard_provider_routing_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar enrutados de proveedor por cliente', tag: 'Provider Routing', responseDto: PaginatedListOutDto::class, itemDto: ProviderRoutingOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page    = max(0, (int) $request->query->get('page', 0));
        $limit   = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->routingRepo->createQueryBuilder('cpr')
            ->leftJoin('cpr.client', 'c')->addSelect('c')
            ->leftJoin('cpr.environment', 'e')->addSelect('e');

        if ($request->query->has('clientId')) {
            $qb->andWhere('cpr.client = :clientId')->setParameter('clientId', (int) $request->query->get('clientId'));
        }
        if ($request->query->has('isActive')) {
            $qb->andWhere('cpr.isActive = :isActive')
               ->setParameter('isActive', filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN));
        }

        $orderParts = explode(' ', $orderBy);
        $field      = self::SORTABLE[$orderParts[0]] ?? 'cpr.id';
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
            'results'     => array_map(fn (ClientProviderRouting $r) => $this->serialize($r), $results),
        ]);
    }

    #[Route('/routing/preview', name: 'dashboard_provider_routing_preview', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Impacto de un cambio de routing antes de aplicarlo', tag: 'Provider Routing', responseDto: ProviderRoutingPreviewOutDto::class)]
    public function preview(Request $request): JsonResponse
    {
        $clientId = (int) $request->query->get('clientId');
        $environmentId = $request->query->has('environmentId') ? (int) $request->query->get('environmentId') : null;
        $saleType = $request->query->get('saleType');
        $provider = (string) $request->query->get('provider');

        if ($clientId <= 0 || $provider === '') {
            return $this->json(['error' => ['message' => 'clientId y provider son obligatorios']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $preview = $this->adminService->preview($clientId, $environmentId, $saleType, $provider);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        $preview->proposedProviderUnregistered = CommunicationProviderEnum::tryFrom($provider) === null
            || !$this->providerRegistry->has(CommunicationProviderEnum::from($provider));

        return $this->json((array) $preview);
    }

    #[Route('/routing/{id}', name: 'dashboard_provider_routing_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener un enrutado de proveedor', tag: 'Provider Routing', responseDto: ProviderRoutingOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $routing = $this->findOrFail($id);
        if ($routing instanceof JsonResponse) {
            return $routing;
        }

        return $this->json($this->serialize($routing));
    }

    #[Route('/routing', name: 'dashboard_provider_routing_create', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Crear enrutado de proveedor', tag: 'Provider Routing', responseDto: ProviderRoutingOutDto::class, responseStatusCode: 201)]
    public function create(CreateProviderRoutingDto $dto): JsonResponse
    {
        try {
            $routing = $this->adminService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($routing), Response::HTTP_CREATED);
    }

    #[Route('/routing/{id}', name: 'dashboard_provider_routing_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Actualizar enrutado de proveedor', tag: 'Provider Routing', responseDto: ProviderRoutingOutDto::class)]
    public function update(int $id, UpdateProviderRoutingDto $dto): JsonResponse
    {
        $routing = $this->findOrFail($id);
        if ($routing instanceof JsonResponse) {
            return $routing;
        }

        try {
            $routing = $this->adminService->update($routing, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($routing));
    }

    #[Route('/routing/{id}/toggle', name: 'dashboard_provider_routing_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Activar o desactivar un enrutado de proveedor', tag: 'Provider Routing', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $routing = $this->findOrFail($id);
        if ($routing instanceof JsonResponse) {
            return $routing;
        }

        try {
            $routing = $this->adminService->toggle($routing);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['id' => $routing->getId(), 'isActive' => $routing->isActive()]);
    }

    #[Route('/routing/{id}', name: 'dashboard_provider_routing_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Eliminar un enrutado de proveedor', tag: 'Provider Routing', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $routing = $this->findOrFail($id);
        if ($routing instanceof JsonResponse) {
            return $routing;
        }

        $this->adminService->delete($routing);

        return $this->json(['deleted' => true]);
    }

    private function findOrFail(int $id): ClientProviderRouting|JsonResponse
    {
        $routing = $this->routingRepo->find($id);
        if ($routing === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        return $routing;
    }

    private function serialize(ClientProviderRouting $routing): array
    {
        return [
            'id'               => $routing->getId(),
            'clientId'         => $routing->getClient()?->getId(),
            'clientName'       => $routing->getClient()?->getCompanyName(),
            'environmentId'    => $routing->getEnvironment()?->getId(),
            'environmentType'  => $routing->getEnvironment()?->getType(),
            'saleType'         => $routing->getSaleType(),
            'provider'         => $routing->getProvider(),
            'fallbackProvider' => $routing->getFallbackProvider(),
            'isActive'         => $routing->isActive(),
            'notes'            => $routing->getNotes(),
            'createdAt'        => $routing->getCreatedAt()?->format('c'),
            'updatedAt'        => $routing->getUpdatedAt()?->format('c'),
        ];
    }
}
