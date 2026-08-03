<?php

namespace App\Provider\Csq;

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

/**
 * Cliente HTTP de bajo nivel para la API "eVSB" de CSQ
 * (https://csq-docs.apidog.io). A fecha de esta integración, la única
 * especificación pública disponible cubre el esquema de autenticación y un
 * endpoint de health-check (`/ping/{echo}`) — los métodos de negocio reales
 * (recarga, venta de paquetes, saldo, catálogo) se entregan por contrato/NDA
 * y todavía no están documentados aquí. Ver CsqCommunicationProvider — hoy
 * expone `getCapabilities(): []` a propósito, sin implementar ninguna
 * interfaz de negocio (RechargeProviderInterface, etc.) hasta tener esa
 * especificación.
 *
 * Credenciales via ProviderCredentialsResolver, mismo esquema genérico
 * `provider.csq.{type}.{campo}` que ETECSA/DTOne, pero con los nombres
 * reales de CSQ (ver CsqCommunicationProvider::getConfigSchema()):
 * `username` = usuario "U" asignado por CSQ, `password` = usado para
 * calcular la firma SH.
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
     * GET /ping/{echo} — único endpoint documentado públicamente hoy. Sirve
     * para verificar de punta a punta que las credenciales y la firma de
     * request funcionan, mientras no haya especificación de los métodos de
     * negocio reales.
     *
     * @return array<string, mixed>
     */
    public function ping(ProviderContext $context, string $echo = 'pong'): array
    {
        $credentials = $this->credentialsResolver->get(CommunicationProviderEnum::CSQ, $context->environmentType);
        if ($credentials['username'] === null || $credentials['password'] === null) {
            throw new MyCurrentException('PROVIDER_CREDENTIALS_MISSING', 'Faltan credenciales de CSQ (usuario/password) para este entorno', 500);
        }

        $baseUrl = $credentials['base_url'] ?? self::DEFAULT_BASE_URL;

        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($baseUrl, '/') . '/ping/' . rawurlencode($echo),
                ['headers' => $this->buildAuthHeaders($credentials['username'], $credentials['password'])],
            );

            return $response->toArray();
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->csqLogger->error('CSQ ping falló', ['error' => $e->getMessage(), 'environmentType' => $context->environmentType]);
            throw new MyCurrentException('CSQ_REQUEST_FAILED', $e->getMessage(), 502);
        }
    }

    /**
     * Headers documentados en "Header Reference" de CSQ. U/ST/SH dependen
     * de las credenciales; el resto son fijos/informativos.
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
            'Accept' => 'json',
            'Accept-Encoding' => 'gzip',
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
