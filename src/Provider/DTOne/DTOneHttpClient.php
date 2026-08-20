<?php

namespace App\Provider\DTOne;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\ProviderCredentialsResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Cliente HTTP de bajo nivel para la API DVS de DTOne
 * (https://developers.dtone.com/reference/overview). HTTP Basic
 * (api_key:api_secret), sin transporte async propio: DTOne despacha con
 * auto_confirm=true y notifica por callback (no autenticado, no
 * reintentado — ver DTOneCommunicationProvider), así que el polling vía
 * getTransactionByExternalId() sigue siendo la red de seguridad.
 *
 * Base URLs documentadas: https://dvs-api.dtone.com (prod),
 * https://preprod-dvs-api.dtone.com (sandbox) — usadas solo como fallback
 * si sys_config no tiene provider.dtone.{env}.base_url configurado.
 */
class DTOneHttpClient
{
    private const DEFAULT_BASE_URL_PROD = 'https://dvs-api.dtone.com';
    private const DEFAULT_BASE_URL_TEST = 'https://preprod-dvs-api.dtone.com';

    /** Error de DTOne cuando external_id ya se usó: no es un fallo real. */
    private const ERROR_DUPLICATE_EXTERNAL_ID = '1007001';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        #[Autowire('@monolog.logger.dtone')] private readonly LoggerInterface $dtoneLogger,
    ) {
    }

    /**
     * POST /v1/async/transactions — crea (y, con auto_confirm, confirma)
     * una transacción. Si el external_id ya se usó, DTOne responde con el
     * error 1007001: no es un fallo, es la señal de que la petición
     * anterior sí llegó. En ese caso se resuelve de forma transparente con
     * findTransactionByExternalId() y se devuelve la transacción real — el
     * llamador nunca debe reintentar la creación ante este error.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function createTransaction(ProviderContext $context, string $externalId, array $body): array
    {
        try {
            return $this->request($context, 'POST', '/v1/async/transactions', $body);
        } catch (DTOneDuplicateExternalIdException) {
            $existing = $this->findTransactionByExternalId($context, $externalId);
            if ($existing !== null) {
                return $existing;
            }

            throw new MyCurrentException(
                'DTONE_DUPLICATE_EXTERNAL_ID_UNRESOLVED',
                "DTOne reportó external_id duplicado ({$externalId}) pero no se pudo recuperar la transacción original",
                502,
            );
        }
    }

    /**
     * GET /v1/transactions?external_id= — recupera una transacción ya
     * creada por su external_id. Único mecanismo fiable para resolver un
     * estado UNKNOWN (timeout, 5xx) o un external_id duplicado.
     *
     * @return array<string, mixed>|null
     */
    public function findTransactionByExternalId(ProviderContext $context, string $externalId): ?array
    {
        $response = $this->request($context, 'GET', '/v1/transactions', null, ['external_id' => $externalId]);
        $items = $response['data'] ?? $response;

        if (is_array($items) && array_is_list($items)) {
            return $items[0] ?? null;
        }

        return is_array($items) ? $items : null;
    }

    /**
     * GET /v1/products — catálogo paginado. DTOne pagina via los headers
     * `X-Total-Pages`/`X-Next-Page`, no via el body.
     *
     * @param array<string, mixed> $query
     * @return iterable<array<string, mixed>>
     */
    public function iterateProducts(ProviderContext $context, array $query = []): iterable
    {
        $page = 1;
        $query['per_page'] ??= 100;

        do {
            $response = $this->requestRaw($context, 'GET', '/v1/products', null, $query + ['page' => $page]);
            $body = json_decode($response->getContent(), true);

            foreach ((array) ($body['data'] ?? $body) as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $headers = $response->getHeaders();
            $totalPages = isset($headers['x-total-pages'][0]) ? (int) $headers['x-total-pages'][0] : 1;
            $page++;
        } while ($page <= $totalPages);
    }

    /**
     * GET /v1/promotions — campañas de DTOne (esquema verificado contra
     * https://developers.dtone.com/reference/getpromotions el 2026-08-18):
     * array plano (NO envuelto en "data"), paginado con los MISMOS headers
     * que /v1/products (X-Total-Pages). Cada promoción trae
     * `{id, title, description, terms, start_date, end_date, operator,
     * products: [{id, name, description, type}]}` — OJO: `products[]` NO
     * trae destinationAmount/benefits, solo id/name/description/type; para
     * esos datos hay que cruzar contra fetchProducts() (ver
     * DTOneCommunicationProvider::fetchPromotionProducts()).
     *
     * @param array<string, mixed> $query
     * @return iterable<array<string, mixed>>
     */
    public function iteratePromotions(ProviderContext $context, array $query = []): iterable
    {
        $page = 1;
        $query['per_page'] ??= 100;

        do {
            $response = $this->requestRaw($context, 'GET', '/v1/promotions', null, $query + ['page' => $page]);
            $body = json_decode($response->getContent(), true);

            foreach ((array) ($body['data'] ?? $body) as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $headers = $response->getHeaders();
            $totalPages = isset($headers['x-total-pages'][0]) ? (int) $headers['x-total-pages'][0] : 1;
            $page++;
        } while ($page <= $totalPages);
    }

    /**
     * GET /v1/balances — nuestro saldo con DTOne, multi-moneda. La
     * respuesta es un array plano de cuentas (no un objeto envoltorio) —
     * ver el docblock de DTOneCommunicationProvider::getPlatformBalance().
     *
     * @return list<array<string, mixed>>
     */
    public function getBalances(ProviderContext $context): array
    {
        /** @var list<array<string, mixed>> */
        return $this->request($context, 'GET', '/v1/balances');
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function request(ProviderContext $context, string $method, string $path, ?array $body = null, array $query = []): array
    {
        $content = $this->requestRaw($context, $method, $path, $body, $query)->getContent();

        return (array) json_decode($content, true);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     */
    private function requestRaw(ProviderContext $context, string $method, string $path, ?array $body, array $query): ResponseInterface
    {
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::DTONE, $context->environmentType);
        $baseUrl = $credentials['base_url'] ?? $this->defaultBaseUrl($context->environmentType);

        if ($credentials['api_key'] === null || $credentials['api_secret'] === null) {
            throw new MyCurrentException(
                'PROVIDER_CREDENTIALS_MISSING',
                'Faltan credenciales de DTOne (provider.dtone.' . strtolower($context->environmentType) . '.api_key/api_secret) en sys_config',
                500,
            );
        }

        $url = rtrim($baseUrl, '/') . $path;
        $start = microtime(true);

        $options = [
            'auth_basic' => [$credentials['api_key'], $credentials['api_secret']],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }
        if ($query !== []) {
            $options['query'] = $query;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            // getContent() es lo que realmente dispara la excepción para
            // 4xx/5xx (getStatusCode() no lo hace) — se fuerza aquí, dentro
            // del try/catch, para traducir el error en este único punto en
            // vez de dejar que escape sin traducir desde request().
            $response->getContent();
            $status = $response->getStatusCode();

            $this->dtoneLogger->info('DTOne gateway call', [
                'path' => $path,
                'method' => $method,
                'env' => $context->environmentType,
                'ms' => round((microtime(true) - $start) * 1000),
                'status' => $status,
            ]);

            return $response;
        } catch (ClientExceptionInterface $e) {
            $errorBody = $this->safeErrorBody($e->getResponse());
            $this->dtoneLogger->error('DTOne client error', [
                'path' => $path,
                'error' => $e->getMessage(),
                'body' => $errorBody,
            ]);

            if ($this->hasErrorCode($errorBody, self::ERROR_DUPLICATE_EXTERNAL_ID)) {
                throw new DTOneDuplicateExternalIdException($e->getMessage(), previous: $e);
            }

            throw new MyCurrentException('DTONE_CLIENT_ERROR', $e->getMessage(), $e->getCode() ?: 400);
        } catch (ServerExceptionInterface $e) {
            $this->dtoneLogger->error('DTOne server error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new MyCurrentException('DTONE_SERVER_ERROR', $e->getMessage(), 502);
        } catch (TransportExceptionInterface|RedirectionExceptionInterface $e) {
            $this->dtoneLogger->error('DTOne transport error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new MyCurrentException('DTONE_GATEWAY_TIMEOUT', $e->getMessage(), 503);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeErrorBody(ResponseInterface $response): array
    {
        try {
            return (array) json_decode($response->getContent(false), true);
        } catch (TransportExceptionInterface) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $errorBody
     */
    private function hasErrorCode(array $errorBody, string $code): bool
    {
        foreach ((array) ($errorBody['errors'] ?? []) as $error) {
            if (is_array($error) && (string) ($error['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    private function defaultBaseUrl(string $environmentType): string
    {
        return strtoupper($environmentType) === 'PROD' ? self::DEFAULT_BASE_URL_PROD : self::DEFAULT_BASE_URL_TEST;
    }
}
