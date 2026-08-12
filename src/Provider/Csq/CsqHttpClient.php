<?php

namespace App\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\ProviderCredentialsResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente HTTP de bajo nivel para la API "eVSB" de CSQ
 * (https://csq-docs.apidog.io — el índice completo con los métodos de
 * negocio, antes no enlazado desde las páginas iniciales, está en
 * `https://csq-docs.apidog.io/llms.txt`). Ver CsqCommunicationProvider —
 * expone CATALOG, RECHARGE y BALANCE; PACKAGE_SALE sigue sin implementar
 * (no hay ningún producto CSQ real tipo PIN_PURCHASE hoy, y
 * PackageSaleRequest no tiene campo de monto — ver el docblock de
 * CsqCommunicationProvider).
 *
 * Credenciales via ProviderCredentialsResolver, mismo esquema genérico
 * `provider.csq.{type}.{campo}` que ETECSA/DTOne, pero con los nombres
 * reales de CSQ (ver CsqCommunicationProvider::getConfigSchema()):
 * `username` = usuario "U" asignado por CSQ, `password` = usado para
 * calcular la firma SH, `terminal` = terminalId asignado por CSQ — no es un
 * header, es dato propio de la cuenta que ya viene en la respuesta de
 * `/product/portfolio` (`terminalId`); ninguno de los dos endpoints
 * implementados hoy lo necesita como parámetro, pero ya se resuelve junto al
 * resto de credenciales.
 */
class CsqHttpClient
{
    private const DEFAULT_BASE_URL = 'https://evsb.csqworld.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        #[Autowire('@monolog.logger.csq')] private readonly LoggerInterface $csqLogger,
    ) {
    }

    /**
     * GET /ping/{echo} — health-check documentado públicamente.
     *
     * @return array<string, mixed>
     */
    public function ping(ProviderContext $context, string $echo = 'pong'): array
    {
        return $this->request($context, 'GET', '/ping/' . rawurlencode($echo));
    }

    /**
     * GET /product/portfolio — catálogo estático completo de CSQ (todos los
     * países, sin filtrar por cuenta): `articleId`, `name`, `countryId`,
     * `country`, `topupType`, `amountType` (`by_list`|`by_range`),
     * `saleAmount{from,to,step,list}`, `exchangeRate`, `saleCurrency`,
     * `destinationCurrency`, `serviceFee`, `productDescription`. Sin
     * parámetros de path ni query (confirmado contra la doc real y con
     * curl el 2026-08-09) — la respuesta es una lista de un solo elemento
     * `{terminalId, products: [...]}` (ver CsqCommunicationProvider::fetchProducts()
     * para el desglose y validación defensiva de esa forma).
     */
    public function getPortfolio(ProviderContext $context): array
    {
        return $this->request($context, 'GET', '/product/portfolio');
    }

    /**
     * POST /pre-paid/recharge/purchase/{terminalId}/{operatorId}/{localReference}
     * — ejecuta una recarga real, de forma síncrona (la respuesta YA es el
     * resultado final: confirmado en vivo el 2026-08-10, ver
     * CsqCommunicationProvider::recharge()). `operatorId` es el `articleId`
     * del portfolio. `localReference`: entero de hasta 9 dígitos, único por
     * día en CSQ — ver CsqCommunicationProvider::deriveLocalReference() para
     * cómo se deriva de nuestro transactionId interno (13 dígitos, no cabe
     * tal cual). Solo se usa `destinationAmountX100` (monto en la moneda de
     * destino × 100) — nunca `amountToSendX100` a la vez: son excluyentes,
     * confirmado en vivo dos veces (2026-08-10 y 2026-08-11) — CSQ rechaza
     * explícito "amountToSendX100 and destinationAmountX100 must not be
     * informed at the same time" si se mandan ambos. destinationAmountX100
     * es el que corresponde a los valores de `saleAmount.list`/`saleAmount`
     * convertido del portfolio (ver docs/csq-product-portfolio.md).
     *
     * `dynamicProductId`/`receiverEmail`/`documentNumber` — confirmado en
     * vivo el 2026-08-10 (compra real exitosa contra Cubacel/7854,
     * `supplierreference "1786346034143"`) y reconfirmado el 2026-08-11:
     * sin estos tres campos CSQ rechaza con `resultcode 991` ("Error de
     * sistema"), **incluso** para un artículo que `GET
     * /pre-paid/recharge/parameters/{terminal}/{operatorId}` marca como
     * `dynamic: false` — ese endpoint no describe fielmente lo que Purchase
     * realmente exige, no usarlo para decidir si mandar estos campos.
     * `dynamicProductId` es el mismo valor que `$operatorId` (como string).
     * `receiverEmail`/`documentNumber` no se capturan por venta (decisión
     * explícita del usuario, 2026-08-11): un valor fijo por entorno,
     * resuelto aquí desde `provider.csq.{env}.default_receiver_email` /
     * `default_document_number` (ver CsqCommunicationProvider::getConfigSchema()).
     *
     * @return array<string, mixed>
     */
    public function purchase(
        ProviderContext $context,
        int $operatorId,
        int $localReference,
        string $account,
        int $destinationAmountX100,
    ): array {
        $terminal = $this->requireTerminal($context);
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::CSQ, $context->environmentType);

        return $this->request(
            $context,
            'POST',
            "/pre-paid/recharge/purchase/{$terminal}/{$operatorId}/{$localReference}",
            [
                'destinationAmountX100' => $destinationAmountX100,
                'localDateTime' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
                'account' => $account,
                'dynamicProductId' => (string) $operatorId,
                'receiverEmail' => $credentials['default_receiver_email'] ?? '',
                'documentNumber' => $credentials['default_document_number'] ?? '',
            ],
        );
    }

    /**
     * GET /util/transaction/info/{terminalId}/{originalLocalReference}/{creationDate}
     * — consulta el estado de una compra ya ejecutada. `creationDate` en
     * formato `yyyyMMdd` (la doc también acepta `ddMMyyyy`, se usa el
     * primero por ser el que no es ambiguo).
     *
     * @return array<string, mixed>
     */
    public function getTransactionInfo(ProviderContext $context, int $localReference, \DateTimeImmutable $creationDate): array
    {
        $terminal = $this->requireTerminal($context);

        return $this->request(
            $context,
            'GET',
            "/util/transaction/info/{$terminal}/{$localReference}/{$creationDate->format('Ymd')}",
        );
    }

    /**
     * GET /external-point-of-sale/by-file/balances — nuestro saldo con CSQ.
     * La doc pública no documenta la forma de la respuesta (schema vacío);
     * confirmado contra el servidor real (2026-08-10):
     * `{"rc":0,"items":[{"id":<terminalId>,"balance":<float>}]}` — `balance`
     * en USD (mismo criterio que `saleCurrency` en el portfolio: es la
     * moneda mayorista universal de CSQ).
     *
     * @return array<string, mixed>
     */
    public function getBalances(ProviderContext $context): array
    {
        return $this->request($context, 'GET', '/external-point-of-sale/by-file/balances');
    }

    private function requireTerminal(ProviderContext $context): int
    {
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::CSQ, $context->environmentType);
        if ($credentials['terminal'] === null) {
            throw new MyCurrentException('PROVIDER_CREDENTIALS_MISSING', 'Falta el terminal de CSQ para este entorno', 500);
        }

        return (int) $credentials['terminal'];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(ProviderContext $context, string $method, string $path, ?array $body = null): array
    {
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::CSQ, $context->environmentType);
        if ($credentials['username'] === null || $credentials['password'] === null) {
            throw new MyCurrentException('PROVIDER_CREDENTIALS_MISSING', 'Faltan credenciales de CSQ (usuario/password) para este entorno', 500);
        }

        $baseUrl = $credentials['base_url'] ?? self::DEFAULT_BASE_URL;
        $options = ['headers' => $this->buildAuthHeaders($credentials['username'], $credentials['password'])];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, rtrim($baseUrl, '/') . $path, $options);

            return $response->toArray();
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface $e) {
            $this->csqLogger->error('CSQ request falló', ['path' => $path, 'method' => $method, 'error' => $e->getMessage(), 'environmentType' => $context->environmentType]);
            throw new MyCurrentException('CSQ_REQUEST_FAILED', $e->getMessage(), 502);
        }
    }

    /**
     * Headers documentados en "Header Reference" de CSQ. U/ST/SH dependen
     * de las credenciales; el resto son fijos/informativos.
     *
     * Bug real encontrado contra el servidor real (2026-08-03): el valor
     * `Accept: json` que la doc da como "ejemplo" literal responde 406 en
     * los endpoints de negocio (`/ping` lo tolera, pero no
     * `/product/portfolio`) — hay que mandar el mimetype completo.
     *
     * Segundo bug real (2026-08-09): NO fijar `Accept-Encoding` a mano.
     * CSQ comprime con gzip las respuestas grandes (`/product/portfolio`,
     * no `/ping`, cuyo body es minúsculo) — Symfony HttpClient
     * (`toArray()`) descomprime automáticamente `Content-Encoding: gzip`
     * en la respuesta, pero SOLO si la petición no fija `Accept-Encoding`
     * ella misma (fijarlo a mano desactiva la negociación/descompresión
     * automática del transporte subyacente). Con el header fijo, el body
     * gzip crudo llegaba tal cual a json_decode y reventaba con "Control
     * character error, possibly incorrectly encoded" — confirmado en vivo
     * contra el servidor real de CSQ.
     *
     * @return array<string, string>
     */
    private function buildAuthHeaders(string $username, string $password): array
    {
        $timestamp = (string) time();

        return [
            'U' => $username,
            'ST' => $timestamp,
            'SH' => $this->computeSignature($password, $timestamp),
            'Accept' => 'application/json',
        ];
    }

    /**
     * SH = sha256hex(sha256hex(password) + sha256hex(ST)) — doble SHA-256,
     * hex en minúsculas. ST (el timestamp) actúa como salt; solo es válido
     * 30 segundos en el servidor de CSQ, así que debe calcularse justo
     * antes de cada request, nunca cachearse.
     */
    public function computeSignature(string $password, string $timestamp): string
    {
        $passwordHash = hash('sha256', $password);
        $saltHash = hash('sha256', $timestamp);

        return hash('sha256', $passwordHash . $saltHash);
    }
}
