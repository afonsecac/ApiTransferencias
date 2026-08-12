<?php

namespace App\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adaptador de CSQ ("eVSB") a la abstracción de proveedor. Implementa el
 * contrato base + CATALOG, RECHARGE y BALANCE. PACKAGE_SALE sigue sin
 * implementar (decisión explícita del usuario, 2026-08-10): hoy no hay
 * ningún producto CSQ real de tipo PIN_PURCHASE (el único sincronizado,
 * Cubacel Pack Combos, es `topupType=Bundles` — recargable, no requiere
 * PackageSaleProviderInterface, ver
 * CommunicationSaleService::assertRechargeableProduct()), y
 * PackageSaleRequest no tiene campo de monto — no encaja con Purchase de
 * CSQ sin adivinar un mapeo sin nada real que probar.
 *
 * Cobertura de catálogo (ver fetchProducts()): solo Cuba (countryId=192,
 * mismo criterio de negocio que DTOneCommunicationProvider::COUNTRY_ISO_CODE
 * — la empresa solo vende Cuba). Corrección de diseño (2026-08-10): a
 * diferencia de los productos RANGED_VALUE_* de DTOne (monto libre elegido
 * por el cliente en el momento de la venta), `amountType=by_range` de CSQ
 * es un conjunto DISCRETO de montos válidos expresado como rango
 * (`from`/`to`/`step`) en vez de lista explícita — se puede tratar como un
 * `by_list` generado, sin necesitar ningún campo de monto variable en el
 * flujo de venta.
 *
 * **`from`/`to`/`step` NO están en la moneda de destino** — confirmado por
 * el usuario contra la documentación de CSQ: hay que dividir por 100 y
 * multiplicar por `exchangeRate` para obtener el monto real en
 * `destinationCurrency` (`realAmount = (value / 100) * exchangeRate`). Esto
 * explica retroactivamente por qué la compra real de prueba (250 CUP)
 * funcionó contra Cubacel pese a estar "fuera" del rango 1100-5500 crudo
 * que se había asumido antes: el rango real es 242-1210 CUP
 * (`(1100/100)*22` a `(5500/100)*22`), y 250 sí cae dentro. `saleAmount.list`
 * en cambio SÍ viene ya en moneda de destino (verificado en vivo: 2200 CUP
 * / exchangeRate 22 = $100 USD exacto) — no aplicarle esta conversión.
 *
 * `$catalogStepCup` (decisión explícita del usuario, 2026-08-10; valor
 * configurable en `config/services.yaml` vía `app.csq.catalog_step_cup`,
 * ver constructor) es nuestro propio step de catálogo — NO el step nativo
 * de CSQ (que para Cubacel es prácticamente continuo, `(1/100)*22=0.22`
 * CUP) — para generar un catálogo manejable: se enumeran los múltiplos de
 * ese step dentro del rango real `[realFrom, realTo]`, opcionalmente
 * acotado por `$catalogMinAmountCup`/`$catalogMaxAmountCup`
 * (`app.csq.catalog_min_amount_cup`/`app.csq.catalog_max_amount_cup`, `~`
 * = sin límite adicional). El purchase real de prueba (250 CUP, no
 * alineado a un múltiplo exacto del step nativo de ningún producto) sugiere
 * que CSQ no valida alineación estricta al step, solo que el monto caiga
 * dentro del rango — si algún monto generado llegara a rechazarse en la
 * práctica, revisar este supuesto.
 *
 * Un producto `by_list` o `by_range` con N montos generados se expande a N
 * `CommunicationProduct`, uno por monto (decisión explícita del usuario
 * 2026-08-09) — la clave de upsert de CommunicationCatalogSyncService es
 * (environment, provider, externalRef), así que cada expansión usa
 * `externalId="{articleId}-{amount}"`.
 *
 * Cobertura de recarga (ver recharge()): a diferencia de ETECSA/DTOne,
 * POST /pre-paid/recharge/purchase de CSQ es SÍNCRONO — la respuesta ya es
 * el resultado final, confirmado en vivo el 2026-08-10 (ver
 * CsqStatusMapper). Por eso recharge() puede devolver COMPLETED
 * directamente, no solo ACCEPTED — CommunicationSaleService::
 * invokeRechargeCommunication() tiene una rama dedicada para finalizar la
 * venta en ese caso (claim atómico + balance + histórico), igual que hace
 * el poll async de checkStatusOrder() para los otros proveedores.
 *
 * Queda registrado igualmente (tag app.communication_provider vía
 * _instanceof en config/services.yaml) para que aparezca en
 * `GET /admin/providers` y en la pantalla de configuración de proveedores
 * del dashboard. Sigue sin estar en `PROVIDER_OPTIONS` del frontend de
 * enrutado (dashboard-cm) — habilitarlo ahí es una decisión de negocio
 * aparte, no solo técnica.
 */
final class CsqCommunicationProvider implements
    CommunicationProviderInterface,
    ProviderHealthCheckInterface,
    ProviderCatalogInterface,
    RechargeProviderInterface,
    ProviderBalanceInterface
{
    /** countryId de Cuba en el portfolio de CSQ (confirmado contra el payload real). */
    private const CUBA_COUNTRY_ID = 192;

    /**
     * topupType -> {service, subservice} en el mismo shape que
     * CommunicationClientPackage::$service, para que
     * ClientCatalogImportService::deriveTags() etiquete igual que con
     * DTOne/ETECSA (subservice.name uppercased contra
     * ['AIRTIME','BUNDLE','DATA','SMS','INTERNET']). 'Data' usa el mismo
     * par service=Utilities/subservice=Internet que DTOne usa para Nauta.
     *
     * @var array<string, array{service: string, subservice: string}>
     */
    private const TOPUP_TYPE_SERVICE_MAP = [
        'RTR' => ['service' => 'Mobile', 'subservice' => 'Airtime'],
        'Bundles' => ['service' => 'Mobile', 'subservice' => 'Bundle'],
        'Data' => ['service' => 'Utilities', 'subservice' => 'Internet'],
    ];

    public function __construct(
        private readonly CsqHttpClient $client,
        private readonly CsqStatusMapper $statusMapper,
        #[Autowire('@monolog.logger.csq')] private readonly LoggerInterface $csqLogger,
        // Configurables en config/services.yaml (app.csq.catalog_*) — ver el
        // docblock de la clase y de amountsFromRange(). Editables sin tocar
        // código para ajustar la granularidad/límites del catálogo generado.
        #[Autowire(param: 'app.csq.catalog_step_cup')] private readonly float $catalogStepCup = 25.0,
        #[Autowire(param: 'app.csq.catalog_min_amount_cup')] private readonly ?float $catalogMinAmountCup = null,
        #[Autowire(param: 'app.csq.catalog_max_amount_cup')] private readonly ?float $catalogMaxAmountCup = null,
    ) {
    }

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::CSQ;
    }

    /**
     * @return list<ProviderCapabilityEnum>
     */
    public function getCapabilities(): array
    {
        return [ProviderCapabilityEnum::CATALOG, ProviderCapabilityEnum::RECHARGE, ProviderCapabilityEnum::BALANCE];
    }

    /**
     * @return list<ProviderConfigField>
     */
    public function getConfigSchema(): array
    {
        return [
            new ProviderConfigField('base_url', 'URL base', required: true, secret: false),
            new ProviderConfigField('username', 'Usuario (U)', required: true, secret: true),
            new ProviderConfigField('password', 'Password', required: true, secret: true),
            new ProviderConfigField('terminal', 'Terminal ID', required: true, secret: false),
            // Confirmado en vivo el 2026-08-10/11: Purchase exige
            // receiverEmail/documentNumber (junto con dynamicProductId) —
            // sin ellos CSQ rechaza con resultcode 991 ("Error de sistema"),
            // incluso para artículos que Get Parameters marca como
            // dynamic:false. No se capturan por venta (decisión explícita
            // del usuario, 2026-08-11): un único valor fijo por entorno, ver
            // CsqHttpClient::purchase().
            new ProviderConfigField('default_receiver_email', 'Email de destinatario (recargas)', required: true, secret: false),
            new ProviderConfigField('default_document_number', 'Documento de identidad (recargas)', required: true, secret: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(ProviderContext $context, string $echo = 'pong'): array
    {
        return $this->client->ping($context, $echo);
    }

    /**
     * Delega en el ping propio de CSQ (GET /ping/{echo}), midiendo latencia
     * localmente porque la API no la devuelve. Un ping fallido de CSQ
     * significa transporte/servidor caído (CsqHttpClient::ping() ya traduce
     * cualquier fallo HTTP a MyCurrentException('CSQ_REQUEST_FAILED')), no
     * un problema de credenciales — no hay clasificación inconclusive aquí.
     */
    public function checkHealth(ProviderContext $context): ProviderPingResult
    {
        $start = microtime(true);

        try {
            $this->client->ping($context);
        } catch (MyCurrentException $e) {
            return ProviderPingResult::unavailable($e->getMessage());
        }

        return ProviderPingResult::available((int) round((microtime(true) - $start) * 1000));
    }

    /**
     * GET /product/portfolio devuelve el catálogo mundial completo en una
     * sola respuesta no paginada (a diferencia de DTOne, no admite filtrar
     * por país en origen) — se filtra aquí mismo por Cuba. Ver el docblock
     * de la clase para el resto de reglas (by_list y by_range, expansión 1
     * producto CSQ -> N CommunicationProduct).
     */
    public function fetchProducts(ProviderContext $context): iterable
    {
        $portfolio = $this->client->getPortfolio($context);
        $products = (array) ($portfolio[0]['products'] ?? []);

        foreach ($products as $item) {
            if (!is_array($item) || !isset($item['articleId']) || (int) ($item['countryId'] ?? 0) !== self::CUBA_COUNTRY_ID) {
                continue;
            }

            $articleId = (string) $item['articleId'];
            $amountType = isset($item['amountType']) ? (string) $item['amountType'] : null;

            $exchangeRate = isset($item['exchangeRate']) ? (float) $item['exchangeRate'] : 0.0;
            if ($exchangeRate <= 0.0) {
                $this->csqLogger->warning('CSQ catálogo: producto omitido, exchangeRate inválido para calcular el precio.', [
                    'articleId' => $articleId,
                    'environmentId' => $context->environmentId,
                ]);

                continue;
            }

            $saleAmount = (array) ($item['saleAmount'] ?? []);
            $amounts = match ($amountType) {
                'by_list' => $this->amountsFromList($saleAmount),
                'by_range' => $this->amountsFromRange($saleAmount, $exchangeRate),
                default => null,
            };

            if ($amounts === null) {
                $this->csqLogger->warning('CSQ catálogo: producto omitido por amountType no soportado.', [
                    'articleId' => $articleId,
                    'amountType' => $amountType,
                    'environmentId' => $context->environmentId,
                ]);

                continue;
            }

            if ($amounts === []) {
                $this->csqLogger->warning('CSQ catálogo: producto by_range omitido, ningún múltiplo de CATALOG_STEP_CUP cae dentro del rango real.', [
                    'articleId' => $articleId,
                    'environmentId' => $context->environmentId,
                ]);

                continue;
            }

            $topupType = isset($item['topupType']) ? (string) $item['topupType'] : null;
            $serviceMap = self::TOPUP_TYPE_SERVICE_MAP[$topupType] ?? null;
            $destinationUnit = isset($item['destinationCurrency']) ? (string) $item['destinationCurrency'] : null;
            $priceCurrency = isset($item['saleCurrency']) ? (string) $item['saleCurrency'] : null;
            $name = (string) ($item['name'] ?? '');
            // CommunicationProduct::$description es varchar(255) — el
            // productDescription real de CSQ puede ser un párrafo entero
            // (confirmado en vivo con "Cubacel  Pack Combos": >255
            // caracteres, reventaba con "value too long for type character
            // varying(255)"). DTOne/ETECSA nunca chocaron con esto porque
            // sus descripciones son cortas; se trunca aquí, específico de
            // CSQ, en vez de migrar una columna compartida por un solo
            // proveedor.
            $description = isset($item['productDescription']) ? $this->truncate((string) $item['productDescription'], 255) : null;

            foreach ($amounts as $destinationAmount) {
                $amountLabel = (string) $destinationAmount;

                yield new ProviderProductDto(
                    externalId: $articleId . '-' . $amountLabel,
                    name: $destinationUnit !== null ? "{$name} - {$amountLabel} {$destinationUnit}" : "{$name} - {$amountLabel}",
                    description: $description,
                    productTypeRaw: $topupType,
                    wholesalePrice: round($destinationAmount / $exchangeRate, 2),
                    priceCurrency: $priceCurrency,
                    destinationAmount: $destinationAmount,
                    destinationMinAmount: null,
                    destinationMaxAmount: null,
                    destinationUnit: $destinationUnit,
                    benefits: [],
                    enabled: true,
                    validFrom: null,
                    validTo: null,
                    raw: $item,
                    isMobileOrInternetService: $serviceMap !== null,
                    service: $serviceMap !== null ? [
                        'name' => $serviceMap['service'],
                        'subservice' => ['name' => $serviceMap['subservice']],
                    ] : [],
                );
            }
        }
    }

    /**
     * `by_list`: `saleAmount.list` ya viene en moneda de destino, sin
     * conversión (verificado en vivo: 2200 CUP / exchangeRate 22 = $100 USD
     * exacto).
     *
     * @param array<string, mixed> $saleAmount
     * @return list<float>
     */
    private function amountsFromList(array $saleAmount): array
    {
        $amounts = [];
        foreach ((array) ($saleAmount['list'] ?? []) as $amount) {
            if (is_numeric($amount)) {
                $amounts[] = (float) $amount;
            }
        }

        return $amounts;
    }

    /**
     * `by_range`: `from`/`to` están en "unidad precio", no en moneda de
     * destino — ver el docblock de la clase para la fórmula
     * (`realAmount = (value / 100) * exchangeRate`) y por qué se usa un
     * step de catálogo propio (`$catalogStepCup`, configurable en
     * `config/services.yaml` vía `app.csq.catalog_step_cup`) en vez del step
     * nativo de CSQ. `$catalogMinAmountCup`/`$catalogMaxAmountCup`
     * (`app.csq.catalog_min_amount_cup`/`app.csq.catalog_max_amount_cup`,
     * también en services.yaml) acotan `[realFrom, realTo]` aún más si están
     * seteados — p.ej. para excluir denominaciones muy bajas/altas del
     * catálogo aunque CSQ las permita. Devuelve los múltiplos de
     * `$catalogStepCup` dentro del rango ya acotado — `[]` si no cabe ningún
     * punto (rango más angosto que un step, o los límites lo cierran del
     * todo).
     *
     * @param array<string, mixed> $saleAmount
     * @return list<float>
     */
    private function amountsFromRange(array $saleAmount, float $exchangeRate): array
    {
        if (!isset($saleAmount['from'], $saleAmount['to']) || !is_numeric($saleAmount['from']) || !is_numeric($saleAmount['to'])) {
            return [];
        }

        $realFrom = ((float) $saleAmount['from'] / 100) * $exchangeRate;
        $realTo = ((float) $saleAmount['to'] / 100) * $exchangeRate;

        if ($this->catalogMinAmountCup !== null) {
            $realFrom = max($realFrom, $this->catalogMinAmountCup);
        }
        if ($this->catalogMaxAmountCup !== null) {
            $realTo = min($realTo, $this->catalogMaxAmountCup);
        }

        $firstPoint = ceil($realFrom / $this->catalogStepCup) * $this->catalogStepCup;
        $lastPoint = floor($realTo / $this->catalogStepCup) * $this->catalogStepCup;

        $amounts = [];
        for ($point = $firstPoint; $point <= $lastPoint + 0.001; $point += $this->catalogStepCup) {
            $amounts[] = $point;
        }

        return $amounts;
    }

    /**
     * POST /pre-paid/recharge/purchase, síncrono (ver docblock de la clase).
     * `$request->productExternalId` es el `externalRef` que
     * fetchProducts()/CommunicationCatalogSyncService persistieron:
     * `"{articleId}-{amount}"` — se parte aquí para recuperar el
     * `operatorId` (articleId) que exige la URL de Purchase. El monto real
     * a cobrar SIEMPRE se toma de esa parte del externalRef (el catálogo
     * `by_list`), nunca de `$request->destinationAmount` — evita que un
     * precio de contrato/promoción alterado en nuestro lado desalinee el
     * monto que de verdad se le pide a CSQ frente al que el cliente vio.
     */
    public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult
    {
        [$articleId, $destinationAmount] = $this->parseProductExternalId($request->productExternalId);
        if ($articleId === null) {
            return new ProviderDispatchResult(
                outcome: ProviderOutcomeEnum::REJECTED,
                message: "productExternalId de CSQ con formato inesperado: \"{$request->productExternalId}\" (se esperaba \"articleId-amount\")",
            );
        }

        try {
            $raw = $this->client->purchase(
                $context,
                $articleId,
                $this->deriveLocalReference($request->transactionId),
                $request->phoneNumber,
                (int) round($destinationAmount * 100),
            );
        } catch (MyCurrentException $e) {
            // Error de transporte (timeout/5xx/decode) — nunca sabemos si
            // CSQ llegó a procesar la compra, así que jamás se puede
            // reintentar el envío mismo (cobraría dos veces). Mismo criterio
            // que ETECSA/DTOne para esta rama.
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
        }

        return $this->statusMapper->mapDispatch($raw);
    }

    /**
     * GET /util/transaction/info tiene delay de indexación (ver
     * CsqStatusMapper::mapStatusQuery() para el hallazgo completo, 2026-08-10)
     * — no está roto, solo tarda en poder consultarse justo después de
     * crear la transacción. Cualquier fallo de transporte también cae a
     * UNKNOWN — nunca REJECTED, por la misma razón que mapStatusQuery()
     * nunca lo hace para un rc!==0.
     */
    public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        $creationDate = \DateTimeImmutable::createFromFormat('ymd', substr($query->transactionId, 0, 6));
        if ($creationDate === false) {
            return new ProviderStatusResult(
                outcome: ProviderOutcomeEnum::UNKNOWN,
                message: "No se pudo derivar la fecha de creación del transactionId \"{$query->transactionId}\"",
            );
        }

        try {
            $raw = $this->client->getTransactionInfo($context, $this->deriveLocalReference($query->transactionId), $creationDate);
        } catch (MyCurrentException $e) {
            return new ProviderStatusResult(outcome: ProviderOutcomeEnum::UNKNOWN, message: $e->getMessage());
        }

        return $this->statusMapper->mapStatusQuery($raw);
    }

    /**
     * GET /external-point-of-sale/by-file/balances. `balance` se asume en
     * USD (mismo criterio que `saleCurrency` del portfolio, universal para
     * todo el catálogo de CSQ) — la doc no lo confirma explícitamente, la
     * respuesta real no trae un campo de moneda.
     */
    public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
    {
        $raw = $this->client->getBalances($context);
        $item = $raw['items'][0] ?? null;
        $amounts = is_array($item) && isset($item['balance']) ? ['USD' => (float) $item['balance']] : [];

        return new ProviderBalanceResult(amounts: $amounts, fetchedAt: new \DateTimeImmutable('now'));
    }

    /**
     * "{articleId}-{amount}" -> [articleId, amount]. `[null, 0.0]` si el
     * formato no coincide (defensivo: un producto no-CSQ o corrupto nunca
     * debería llegar aquí, pero un 500 crudo por una excepción de tipo es
     * peor que un REJECTED con mensaje claro).
     *
     * @return array{0: ?int, 1: float}
     */
    private function parseProductExternalId(string $externalId): array
    {
        $parts = explode('-', $externalId, 2);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return [null, 0.0];
        }

        return [(int) $parts[0], (float) $parts[1]];
    }

    /**
     * CSQ exige un `localReference` numérico de hasta 9 dígitos, único por
     * día. Nuestro transactionId interno tiene 13 dígitos:
     * `YYMMDD` (6) + tipo de venta (2) + secuencia (5) — ver
     * CommunicationSaleService::processRecharge()/processReserve(). Los
     * primeros 6 dígitos (la fecha) son redundantes con el `creationDate`
     * que CSQ ya recibe/deriva por su cuenta — se descartan, dejando
     * "tipo+secuencia" (7 dígitos), que ya es único dentro del día porque
     * la secuencia es un contador creciente por transacción.
     */
    private function deriveLocalReference(string $transactionId): int
    {
        return (int) substr($transactionId, 6);
    }

    private function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }
}
