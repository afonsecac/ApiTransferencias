<?php

namespace App\Controller;

use App\DTO\CreateProviderRoutingDto;
use App\DTO\Out\CurrencyExchangeRateOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\ExchangeRateSyncOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ProviderRoutingOutDto;
use App\DTO\Out\ProviderRoutingPreviewOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\SetProviderActiveDto;
use App\DTO\UpdateProviderCredentialsDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\ClientProviderRouting;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CurrencyExchangeRateRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\CurrencyExchangeRateSyncService;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderConnectionTestService;
use App\Service\Provider\ProviderCredentialsAdminService;
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
        private readonly CurrencyExchangeRateSyncService $exchangeRateSyncService,
        private readonly CurrencyExchangeRateRepository $exchangeRateRepo,
        private readonly ProviderCredentialsAdminService $credentialsAdminService,
        private readonly ProviderConnectionTestService $connectionTestService,
        private readonly ProviderAvailabilityService $availabilityService,
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
                // Nunca incluye valores, solo la forma del esquema (nombre,
                // obligatoriedad, si se cifra) — la UI la usa para renderizar
                // los campos correctos por proveedor.
                'configSchema' => array_map(fn ($field) => [
                    'key' => $field->key,
                    'label' => $field->label,
                    'required' => $field->required,
                    'secret' => $field->secret,
                ], $this->providerRegistry->get($code)->getConfigSchema()),
            ], $registered),
            'routingEnabled' => $this->sysConfigRepo->findCachedValue(ProviderResolver::ROUTING_ENABLED_KEY) !== '0',
            'defaultProvider' => $this->sysConfigRepo->findCachedValue(ProviderResolver::DEFAULT_PROVIDER_KEY) ?? CommunicationProviderEnum::ETECSA->value,
        ]);
    }

    #[Route('/{code}/credentials', name: 'dashboard_provider_credentials_show', methods: ['GET'], requirements: ['code' => 'ETECSA|DTONE|CSQ'])]
    #[DashboardEndpoint(summary: 'Estado de las credenciales configuradas para un proveedor (TEST/PROD)', tag: 'Provider Routing')]
    public function credentialsStatus(string $code): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->credentialsAdminService->getStatus($provider));
    }

    #[Route('/{code}/credentials/{environmentType}', name: 'dashboard_provider_credentials_update', methods: ['PATCH'], requirements: ['code' => 'ETECSA|DTONE|CSQ', 'environmentType' => 'TEST|PROD'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Actualizar las credenciales de un proveedor para un entorno', tag: 'Provider Routing')]
    public function updateCredentials(string $code, string $environmentType, UpdateProviderCredentialsDto $dto): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->credentialsAdminService->upsert($provider, $environmentType, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->credentialsAdminService->getStatus($provider)[strtolower($environmentType)]);
    }

    #[Route('/{code}/active/{environmentType}', name: 'dashboard_provider_active_set', methods: ['PATCH'], requirements: ['code' => 'ETECSA|DTONE|CSQ', 'environmentType' => 'TEST|PROD'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Activar o desactivar manualmente un proveedor para un entorno', tag: 'Provider Routing')]
    public function setActive(string $code, string $environmentType, SetProviderActiveDto $dto): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        try {
            // Pasa por ProviderAvailabilityService (no directo a
            // ProviderCredentialsAdminService) para que audite el usuario y
            // aplique el requisito "no se puede activar el despacho si el
            // proveedor no está bien configurado" (409 PROVIDER_NOT_CONFIGURED).
            $this->availabilityService->setManual($provider, $environmentType, $dto->getActive() ?? true);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->credentialsAdminService->getStatus($provider));
    }

    #[Route('/{code}/test-connection', name: 'dashboard_provider_test_connection', methods: ['GET'], requirements: ['code' => 'ETECSA|DTONE|CSQ'])]
    #[DashboardEndpoint(summary: 'Probar en vivo si las credenciales configuradas funcionan (consulta de saldo)', tag: 'Provider Routing')]
    public function testConnection(string $code, Request $request): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        $environmentType = strtoupper((string) $request->query->get('environmentType', 'PROD'));
        if (!in_array($environmentType, ['TEST', 'PROD'], true)) {
            return $this->json(['error' => ['message' => 'environmentType debe ser TEST o PROD']], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->connectionTestService->test($provider, $environmentType));
    }

    /**
     * Sincronización manual del histórico de tasas de cambio (Frankfurter),
     * para cuando un admin necesita refrescarlo antes de que corra el cron
     * programado (ver app:provider:sync-exchange-rates). ROLE_ADMIN basta:
     * no mueve dinero ni cambia routing, solo refresca datos de referencia.
     */
    #[Route('/exchange-rates/sync', name: 'dashboard_provider_exchange_rates_sync', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Sincronizar el histórico de tasas de cambio (Frankfurter)', tag: 'Provider Routing', responseDto: ExchangeRateSyncOutDto::class)]
    public function syncExchangeRates(): JsonResponse
    {
        try {
            $result = $this->exchangeRateSyncService->sync();
        } catch (\Throwable $e) {
            return $this->json(
                ['error' => ['message' => 'No se pudo sincronizar el tipo de cambio: ' . $e->getMessage()]],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return $this->json([
            'created' => $result->created,
            'rateDate' => $result->rateDate,
            'baseCurrency' => $result->baseCurrency,
        ]);
    }

    /**
     * Histórico de tasas ya guardadas — para auditar qué tasa se usó en un
     * momento dado (ver conversionRate/conversionRateDate en
     * CommunicationPricePackage, poblados por ClientCatalogImportService).
     */
    #[Route('/exchange-rates', name: 'dashboard_provider_exchange_rates_history', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Histórico de tasas de cambio guardadas', tag: 'Provider Routing', responseDto: PaginatedListOutDto::class, itemDto: CurrencyExchangeRateOutDto::class)]
    public function exchangeRatesHistory(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $targetCurrency = $request->query->get('targetCurrency');
        $targetCurrency = $targetCurrency !== null ? strtoupper((string) $targetCurrency) : null;

        $total = $this->exchangeRateRepo->countHistory($targetCurrency);
        $results = $this->exchangeRateRepo->findHistory($targetCurrency, $limit + 1, $page * $limit);

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'total' => $total,
            'results' => array_map(static fn ($rate) => [
                'id' => $rate->getId(),
                'baseCurrency' => $rate->getBaseCurrency(),
                'targetCurrency' => $rate->getTargetCurrency(),
                'rate' => $rate->getRate(),
                'rateDate' => $rate->getRateDate()?->format('Y-m-d'),
                'fetchedAt' => $rate->getFetchedAt()?->format(\DateTimeInterface::ATOM),
            ], $results),
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
