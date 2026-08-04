<?php

namespace App\Provider\Etecsa;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\GeoCatalogProviderInterface;
use App\Provider\Contract\PackageSaleProviderInterface;
use App\Provider\Contract\PackageSaleRequest;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderBalanceResult;
use App\Provider\Contract\ProviderCatalogInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderHealthCheckInterface;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\Contract\ProviderProductDto;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\ProviderStatusResult;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\RechargeRequest;
use App\Provider\Contract\TouristSimProviderInterface;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaGatewayClient;

/**
 * Adaptador de ETECSA a la abstracción de proveedor. Envuelve
 * EtecsaGatewayClient (que NO se reescribe: sigue siendo el único punto de
 * transporte HTTP hacia ApiComm) y traduce ProviderContext <-> Environment,
 * y arrays crudos <-> DTOs neutrales vía EtecsaStatusMapper.
 *
 * Las excepciones de EtecsaGatewayClient (MyCurrentException con codeWork
 * ETECSA_CLIENT_ERROR/ETECSA_SERVER_ERROR/ETECSA_GATEWAY_TIMEOUT) se tratan
 * distinto según la operación:
 *
 *  - recharge()/sellPackage() (envío): se capturan y se traducen a
 *    ProviderOutcomeEnum::UNKNOWN. Esto reproduce el comportamiento REAL de
 *    hoy (no el que aparentan los catch específicos de
 *    CommunicationSaleService::invokeRechargeCommunication, que son código
 *    muerto: MyCurrentException no implementa ninguna de esas interfaces de
 *    Symfony HttpClient, así que esas excepciones siempre terminaban en el
 *    catch(\Exception) genérico, que las deja en PENDING con mensaje
 *    genérico). No sabemos si la petición llegó a ETECSA, así que jamás debe
 *    reintentarse automáticamente el envío.
 *
 *  - fetchRechargeStatus()/fetchPackageSaleStatus() (consulta): la excepción
 *    se deja propagar tal cual. El llamador (CommunicationSaleService)
 *    inspecciona el código HTTP (404/400/otros) para decidir su lógica de
 *    reintento con backoff — eso es orquestación de negocio, no pertenece
 *    a este adaptador.
 */
final class EtecsaCommunicationProvider implements
    RechargeProviderInterface,
    PackageSaleProviderInterface,
    ProviderBalanceInterface,
    ProviderCatalogInterface,
    TouristSimProviderInterface,
    GeoCatalogProviderInterface,
    ProviderHealthCheckInterface
{
    public function __construct(
        private readonly EtecsaGatewayClient $client,
        private readonly EtecsaStatusMapper $statusMapper,
        private readonly EnvironmentRepository $environmentRepository,
    ) {
    }

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::ETECSA;
    }

    /**
     * @return list<ProviderCapabilityEnum>
     */
    public function getCapabilities(): array
    {
        return [
            ProviderCapabilityEnum::RECHARGE,
            ProviderCapabilityEnum::PACKAGE_SALE,
            ProviderCapabilityEnum::BALANCE,
            ProviderCapabilityEnum::CATALOG,
            ProviderCapabilityEnum::TOURIST_SIM,
            ProviderCapabilityEnum::GEO_CATALOG,
        ];
    }

    /**
     * @return list<ProviderConfigField>
     */
    public function getConfigSchema(): array
    {
        return [
            new ProviderConfigField('base_url', 'URL base', required: true, secret: false),
            new ProviderConfigField('api_key', 'API key', required: true, secret: true),
        ];
    }

    public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult
    {
        $env = $this->resolveEnvironment($context);

        try {
            $raw = $this->client->recharge(
                $env,
                $request->phoneNumber,
                (int) $request->productExternalId,
                $request->destinationAmount,
                $request->transactionId,
            );
        } catch (MyCurrentException $e) {
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
        }

        return $this->statusMapper->mapRechargeDispatch($raw);
    }

    public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->getSaleInfo($env, $query->transactionId);

        return $this->statusMapper->mapStatusQuery($raw, isRecharge: true);
    }

    public function sellPackage(ProviderContext $context, PackageSaleRequest $request): ProviderDispatchResult
    {
        $env = $this->resolveEnvironment($context);

        $customer = $request->customer;
        $salePoint = $request->salePoint;

        $packageInfo = [
            'id' => $request->productExternalId,
            'packageType' => $request->productKind,
        ];
        $client = [
            'id' => $customer?->identificationNumber,
            'name' => $customer?->name,
            'identificationType' => $customer !== null ? ($customer->identificationType ?? 1) : 1,
            'arrivalDate' => $customer?->arrivalDate?->format('Y-m-d'),
            'isAirport' => $salePoint?->isAirport,
            'commercialOfficeId' => $salePoint?->commercialOfficeExternalId,
            'provinceId' => $salePoint?->provinceExternalId,
            'nationality' => $customer?->nationalityExternalId,
        ];

        try {
            $raw = $this->client->sellPackage($env, $request->transactionId, $packageInfo, $client, $request->phoneNumber);
        } catch (MyCurrentException $e) {
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
        }

        return $this->statusMapper->mapSaleDispatch($raw);
    }

    public function fetchPackageSaleStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->getSaleInfo($env, $query->transactionId);

        return $this->statusMapper->mapStatusQuery($raw, isRecharge: false);
    }

    public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
    {
        $env = $this->resolveEnvironment($context);
        $balance = $this->client->getBalance($env);

        return new ProviderBalanceResult(
            amounts: ['CUP' => $balance->cupAmount, 'USD' => $balance->usdAmount],
            fetchedAt: $balance->fetchedAt,
        );
    }

    /**
     * GET /ping. 200 y 503 comparten forma de respuesta — el status HTTP y
     * `available` en el body son la misma señal, redundante. 401/403/409
     * significan credencial/permiso mal configurado, no un ETECSA caído:
     * se reportan como inconclusive para que
     * App\Service\Provider\ProviderAvailabilityService no apague el
     * proveedor por un falso positivo nuestro.
     */
    public function checkHealth(ProviderContext $context): ProviderPingResult
    {
        $env = $this->resolveEnvironment($context);

        try {
            $result = $this->client->ping($env);
        } catch (MyCurrentException $e) {
            return ProviderPingResult::unavailable($e->getMessage());
        }

        $status = $result['status'];
        $body = $result['body'];
        $latencyMs = isset($body['latencyMs']) && is_numeric($body['latencyMs']) ? (int) $body['latencyMs'] : null;

        return match (true) {
            $status === 200 => ProviderPingResult::available($latencyMs, $body),
            $status === 503 => ProviderPingResult::unavailable(
                is_string($body['error'] ?? null) ? $body['error'] : 'ETECSA: servicio no disponible',
                $latencyMs,
                $body,
            ),
            in_array($status, [401, 403, 409], true) => ProviderPingResult::inconclusive(
                "ETECSA ping: respuesta HTTP {$status} — revisar credenciales/scope",
                $body,
            ),
            default => ProviderPingResult::inconclusive("ETECSA ping: status HTTP inesperado {$status}", $body),
        };
    }

    public function fetchProducts(ProviderContext $context): iterable
    {
        $env = $this->resolveEnvironment($context);

        foreach ($this->client->listPackages($env) as $raw) {
            $item = (object) $raw;
            if (!isset($item->Id)) {
                continue;
            }

            yield new ProviderProductDto(
                externalId: (string) $item->Id,
                name: (string) ($item->Description ?? ''),
                description: (string) ($item->Description ?? ''),
                productTypeRaw: (string) ($item->PackageType ?? ''),
                wholesalePrice: (float) ($item->Price ?? 0.0),
                priceCurrency: null,
                destinationAmount: null,
                destinationMinAmount: null,
                destinationMaxAmount: null,
                destinationUnit: null,
                benefits: [],
                enabled: (bool) ($item->Enabled ?? false),
                validFrom: !empty($item->InitialDate) ? new \DateTimeImmutable($item->InitialDate) : null,
                validTo: !empty($item->FinalDate) ? new \DateTimeImmutable($item->FinalDate) : null,
                raw: (array) $raw,
                // ETECSA solo vende telefonía móvil (Cubacel) — no hay otra
                // categoría de producto que distinguir.
                isMobileOrInternetService: true,
                service: ['name' => 'MOBILE', 'subservice' => ['name' => 'AIRTIME']],
            );
        }
    }

    public function checkPhone(ProviderContext $context, string $phoneNumber): ProviderStatusResult
    {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->checkPhone($env, $phoneNumber);

        return new ProviderStatusResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
    }

    public function sellTouristSim(
        ProviderContext $context,
        string $transactionId,
        string $phoneNumber,
        string $packageCode,
        ?array $client = null,
    ): ProviderDispatchResult {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->sellTur($env, $transactionId, $phoneNumber, $packageCode, $client);

        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
    }

    public function sellTouristSimBatch(
        ProviderContext $context,
        string $transactionId,
        string $packageCode,
        array $clients = [],
    ): ProviderDispatchResult {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->sellTurBatch($env, $transactionId, $packageCode, $clients);

        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
    }

    public function fetchTouristSaleInfo(ProviderContext $context, string $transactionId, ?string $orderId = null): ProviderStatusResult
    {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->getTurSaleInfo($env, $transactionId, $orderId);

        return new ProviderStatusResult(outcome: ProviderOutcomeEnum::PENDING, raw: $raw);
    }

    public function fetchTouristBatchInfo(ProviderContext $context, string $transactionId, ?string $orderId = null): ProviderStatusResult
    {
        $env = $this->resolveEnvironment($context);
        $raw = $this->client->getTurBatchInfo($env, $transactionId, $orderId);

        return new ProviderStatusResult(outcome: ProviderOutcomeEnum::PENDING, raw: $raw);
    }

    public function fetchNationalities(ProviderContext $context): iterable
    {
        return $this->client->listNationalities($this->resolveEnvironment($context));
    }

    public function fetchProvinces(ProviderContext $context): iterable
    {
        return $this->client->listProvinces($this->resolveEnvironment($context));
    }

    public function fetchCommercialOffices(ProviderContext $context, ?int $provinceExternalId = null): iterable
    {
        return $this->client->listCommercialOffices($this->resolveEnvironment($context), $provinceExternalId);
    }

    private function resolveEnvironment(ProviderContext $context): Environment
    {
        if ($context->environmentId !== null) {
            $env = $this->environmentRepository->find($context->environmentId);
            if ($env instanceof Environment) {
                return $env;
            }
        }

        $env = $this->environmentRepository->findOneBy([
            'type' => $context->environmentType,
            'scope' => 'ET',
            'isActive' => true,
        ]);

        if (!$env instanceof Environment) {
            throw new MyCurrentException(
                'PROVIDER_ENVIRONMENT_NOT_FOUND',
                'No se encontró un Environment ETECSA activo para este contexto',
                500,
            );
        }

        return $env;
    }
}
