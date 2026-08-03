<?php

namespace App\Provider\DTOne;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\PackageSaleProviderInterface;
use App\Provider\Contract\PackageSaleRequest;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderBalanceResult;
use App\Provider\Contract\ProviderCatalogInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderProductDto;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\ProviderStatusResult;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\RechargeRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adaptador de DTOne (https://developers.dtone.com) a la abstracción de
 * proveedor. DTOne no distingue recarga de venta de paquete a nivel de
 * transporte — ambas son la misma operación (POST /v1/async/transactions
 * con un product_id), así que recharge() y sellPackage() comparten
 * buildTransactionBody().
 *
 * Se usa auto_confirm=true (ver plan): el flujo en dos pasos create->confirm
 * añadiría un estado intermedio y el CANCELLED automático a 60 min que no
 * aporta nada en la primera entrega.
 *
 * Cobertura de producto (ver fetchProducts()): `FIXED_VALUE_RECHARGE` y
 * `FIXED_VALUE_PIN_PURCHASE` — este último se vende siempre como paquete,
 * nunca como recarga (ver guard en CommunicationSaleService). Los productos
 * `RANGED_VALUE_*` se excluyen del sync: no hay ninguno habilitado hoy en
 * la cuenta DTOne del cliente, y admitirlos requeriría expandir el rango en
 * varios productos de precio fijo (columna de rango + revisión humana del
 * salto) — se implementará cuando haya un producto real que lo necesite.
 * `fetchProducts()` además filtra en origen por `country_iso_code=CUB`
 * (ver COUNTRY_ISO_CODE): el negocio solo vende Cuba, y sin ese filtro
 * DTOne devuelve su catálogo mundial completo. También calcula
 * `ProviderProductDto::$isMobileOrInternetService` a partir de
 * `service`/`service.subservice` — es lo que usa
 * ClientCatalogImportService::matchesSaleType() para decidir si un
 * producto aplica a un enrutado con `saleType='recharge'` (solo móvil o
 * Internet) o `'sale'` (el resto: gift cards, SIM/equipos, comida...).
 */
final class DTOneCommunicationProvider implements
    RechargeProviderInterface,
    PackageSaleProviderInterface,
    ProviderBalanceInterface,
    ProviderCatalogInterface
{
    /**
     * Valores de `type` (clasificación de producto de DTOne) soportados hoy
     * por fetchProducts(). Cualquier otro valor (RANGED_VALUE_*, o un tipo
     * desconocido) se omite con un log de advertencia — nunca en silencio.
     */
    private const SUPPORTED_PRODUCT_TYPES = ['FIXED_VALUE_RECHARGE', 'FIXED_VALUE_PIN_PURCHASE'];

    /**
     * El negocio solo vende productos de Cuba — sin este filtro,
     * GET /v1/products devuelve el catálogo mundial completo de DTOne
     * (decenas de miles de productos, la inmensa mayoría RANGED_VALUE_* de
     * otros países) y agota la memoria del proceso antes de terminar de
     * paginar (confirmado el 2026-08-02: OOM a los ~49000 productos, sin
     * haber persistido nada porque CommunicationCatalogSyncService solo hace
     * flush() al final). Filtrar en origen por country_iso_code es muchísimo
     * más barato que traer todo y descartar en el cliente.
     */
    private const COUNTRY_ISO_CODE = 'CUB';

    /**
     * DTOne exige el número en E.164 completo (`+` + código de país + número
     * local) para `credit_party_identifier.mobile_number` — confirmado
     * contra su documentación pública y en vivo el 2026-08-03 (un número sin
     * "+"/código de país se rechaza con "mobile_number is invalid" antes de
     * siquiera validar si el número es real).
     *
     * Un número cubano completo (código de país + abonado) tiene 10 dígitos
     * en total ("53" + 8 del abonado) — por eso
     * CommunicationSaleRecharge::$phoneNumber admite de 8 a 10 dígitos
     * (Assert\Length(min: 8, max: 10)): 8-9 dígitos = solo el número local
     * (sin código de país, convenio histórico de ETECSA), 10 dígitos = ya
     * viene con el código de país incluido. La conversión a E.164 (ver
     * toE164Cuba()) tiene que cubrir ambos casos.
     */
    private const COUNTRY_CALLING_CODE = '53';

    public function __construct(
        private readonly DTOneHttpClient $client,
        private readonly DTOneStatusMapper $statusMapper,
        #[Autowire('@monolog.logger.dtone')]
        private readonly LoggerInterface $dtoneLogger,
    ) {
    }

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::DTONE;
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
            new ProviderConfigField('api_secret', 'API secret', required: true, secret: true),
        ];
    }

    public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult
    {
        $body = $this->buildTransactionBody($request->transactionId, $request->productExternalId, $request->phoneNumber);

        try {
            $raw = $this->client->createTransaction($context, $request->transactionId, $body);
        } catch (MyCurrentException $e) {
            return $this->mapDispatchException($e);
        }

        return $this->statusMapper->mapDispatch($raw);
    }

    public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        return $this->fetchStatus($context, $query);
    }

    public function sellPackage(ProviderContext $context, PackageSaleRequest $request): ProviderDispatchResult
    {
        $body = $this->buildTransactionBody($request->transactionId, $request->productExternalId, $request->phoneNumber);

        try {
            $raw = $this->client->createTransaction($context, $request->transactionId, $body);
        } catch (MyCurrentException $e) {
            return $this->mapDispatchException($e);
        }

        return $this->statusMapper->mapDispatch($raw);
    }

    /**
     * DTONE_CLIENT_ERROR (ver DTOneHttpClient::requestRaw()) es una
     * respuesta 4xx definitiva de DTOne — la transacción nunca llegó a
     * crearse, así que se puede rechazar de inmediato. Cualquier otro caso
     * (timeout, 5xx, credenciales) es genuinamente ambiguo: no sabemos si
     * DTOne procesó la petición, así que se deja como UNKNOWN para que
     * CheckStatusTask lo resuelva reconsultando el estado más tarde — nunca
     * reintentar el ENVÍO mismo ante un UNKNOWN (cobraría dos veces).
     *
     * Antes de este fix (2026-08-03) todo error, incluido un 4xx
     * definitivo, se mapeaba a UNKNOWN — una venta rechazada así quedaba en
     * Pending para siempre: CheckStatusTask reconsultaba la transacción,
     * DTOne respondía "no encontrada" (nunca se creó) y eso TAMBIÉN se
     * mapeaba a UNKNOWN (ver fetchStatus()), sin salida posible del ciclo.
     */
    private function mapDispatchException(MyCurrentException $e): ProviderDispatchResult
    {
        if ($e->getCodeWork() === 'DTONE_CLIENT_ERROR') {
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED, message: $e->getMessage());
        }

        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
    }

    public function fetchPackageSaleStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        return $this->fetchStatus($context, $query);
    }

    private function fetchStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        try {
            $raw = $this->client->findTransactionByExternalId($context, $query->transactionId);
        } catch (MyCurrentException $e) {
            return new ProviderStatusResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
        }

        if ($raw === null) {
            // DTOne usa auto_confirm=true (sin paso de confirmación
            // separado): una transacción real queda indexada de inmediato,
            // así que "no encontrada" aquí significa con confianza que
            // nunca se creó — no es ambiguo como un timeout, se puede
            // rechazar de una vez en vez de dejarlo reintentando para
            // siempre (ver mapDispatchException()).
            return new ProviderStatusResult(
                outcome: ProviderOutcomeEnum::REJECTED,
                message: "No se encontró en DTOne ninguna transacción con external_id={$query->transactionId}",
            );
        }

        return $this->statusMapper->mapStatusQuery($raw);
    }

    public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
    {
        $raw = $this->client->getBalances($context);
        $amounts = [];

        foreach ((array) ($raw['data'] ?? $raw['balances'] ?? []) as $entry) {
            if (!is_array($entry) || !isset($entry['currency'])) {
                continue;
            }

            $amounts[(string) $entry['currency']] = (float) ($entry['amount'] ?? 0.0);
        }

        return new ProviderBalanceResult(amounts: $amounts, fetchedAt: new \DateTimeImmutable('now'));
    }

    public function fetchProducts(ProviderContext $context): iterable
    {
        foreach ($this->client->iterateProducts($context, ['country_iso_code' => self::COUNTRY_ISO_CODE]) as $item) {
            if (!isset($item['id'])) {
                continue;
            }

            // El campo de clasificación real de DTOne es `type`, a nivel raíz
            // del producto — `product_type` no existe en la respuesta real de
            // /v1/products (verificado contra el payload crudo del sandbox el
            // 2026-08-02). No confundir con el `type` anidado en cada
            // elemento de `benefits[]` (p.ej. "CREDITS"), que es un campo
            // distinto con otro significado.
            $productType = isset($item['type']) ? (string) $item['type'] : null;

            if ($productType === null || !in_array($productType, self::SUPPORTED_PRODUCT_TYPES, true)) {
                $this->dtoneLogger->warning('DTOne catálogo: producto omitido por tipo no soportado.', [
                    'productId' => $item['id'],
                    'productType' => $productType,
                    'environmentId' => $context->environmentId,
                ]);

                continue;
            }

            $destination = (array) ($item['destination'] ?? []);
            $wholesale = (array) ($item['prices']['wholesale'] ?? []);

            // Clasificación real de DTOne por `service`/`service.subservice`
            // (verificado contra el catálogo real de Cuba el 2026-08-02):
            // `service=Mobile` (Airtime/Data/Bundle de CubaCel) y
            // `service=Utilities` con subservice Internet/Landline (Nauta)
            // son los únicos que de verdad son servicio móvil o Internet.
            // Todo lo demás — `Utilities` sin subservice (SIM cards,
            // equipos) y `Gift Cards` (comida, mandados) — no lo es, aunque
            // DTOne lo entregue igual como FIXED_VALUE_RECHARGE.
            $service = isset($item['service']['name']) ? (string) $item['service']['name'] : null;
            $subservice = isset($item['service']['subservice']['name']) ? (string) $item['service']['subservice']['name'] : null;
            $isMobileOrInternetService = $service === 'Mobile'
                || ($service === 'Utilities' && in_array($subservice, ['Internet', 'Landline'], true));

            yield new ProviderProductDto(
                externalId: (string) $item['id'],
                name: (string) ($item['name'] ?? ''),
                description: isset($item['description']) ? (string) $item['description'] : null,
                productTypeRaw: $productType,
                wholesalePrice: (float) ($wholesale['amount'] ?? 0.0),
                priceCurrency: isset($wholesale['unit']) ? (string) $wholesale['unit'] : null,
                destinationAmount: isset($destination['amount']) ? (float) $destination['amount'] : null,
                destinationMinAmount: isset($destination['min_amount']) ? (float) $destination['min_amount'] : null,
                destinationMaxAmount: isset($destination['max_amount']) ? (float) $destination['max_amount'] : null,
                destinationUnit: isset($destination['unit']) ? (string) $destination['unit'] : null,
                benefits: (array) ($item['benefits'] ?? []),
                enabled: (bool) ($item['is_active'] ?? true),
                validFrom: null,
                validTo: null,
                raw: $item,
                isMobileOrInternetService: $isMobileOrInternetService,
                service: array_filter([
                    'name' => $service,
                    'subservice' => $subservice !== null ? ['name' => $subservice] : null,
                ], static fn ($v) => $v !== null),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTransactionBody(string $transactionId, string $productExternalId, ?string $phoneNumber): array
    {
        $body = [
            'external_id' => $transactionId,
            'product_id' => (int) $productExternalId,
            'auto_confirm' => true,
        ];

        if ($phoneNumber !== null) {
            $body['credit_party_identifier'] = ['mobile_number' => $this->toE164Cuba($phoneNumber)];
        }

        return $body;
    }

    /**
     * Convierte el número local de Cuba (convenio de
     * CommunicationSaleRecharge::$phoneNumber, ver COUNTRY_CALLING_CODE) al
     * E.164 completo que exige DTOne.
     */
    private function toE164Cuba(string $phoneNumber): string
    {
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        // 10 dígitos = ya incluye el código de país ("53" + 8 del abonado).
        if (strlen($phoneNumber) === 10 && str_starts_with($phoneNumber, self::COUNTRY_CALLING_CODE)) {
            return '+' . $phoneNumber;
        }

        return '+' . self::COUNTRY_CALLING_CODE . $phoneNumber;
    }
}
