<?php

namespace App\Service\Etecsa;

use App\DTO\Etecsa\EtecsaBalanceDto;
use App\DTO\Out\InfoResult;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\CommonService;

class EtecsaGatewayClient extends CommonService
{
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        SerializerInterface $serializer,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('@monolog.logger.etecsa')] private readonly LoggerInterface $etecsaLogger,
        #[Autowire('%env(APP_TEST_PHONE)%')] private readonly string $testPhone,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }

    /**
     * POST /sale/recharge — iniciar recarga de saldo.
     */
    public function recharge(
        Environment $env,
        string $phoneNumber,
        int $productCode,
        float $productPrice,
        string $transactionId,
    ): array {
        if ($env->getType() === 'TEST' && str_ends_with($phoneNumber, '60')) {
            $phoneNumber = $this->testPhone;
        }

        $body = [
            'phoneNumber' => $phoneNumber,
            'productCode' => $productCode,
            'productPrice' => round($productPrice, 2),
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
        ];

        return $this->post($env, '/sale/recharge', $body);
    }

    /**
     * POST /sale/package — venta de paquete turístico.
     *
     * @param array{id: string|null, packageType: string|null} $packageInfo
     * @param array{id: string|null, name: string|null, identificationType: int, arrivalDate: string|null, isAirport: bool|null, commercialOfficeId: int|null, provinceId: int|null, nationality: int|null} $client
     */
    public function sellPackage(
        Environment $env,
        string $transactionId,
        array $packageInfo,
        array $client,
        ?string $phoneNumber = null,
    ): array {
        $body = [
            'packageInfo' => $packageInfo,
            'client' => $client,
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
        ];

        if ($phoneNumber !== null) {
            $body['phoneNumber'] = $phoneNumber;
        }

        return $this->post($env, '/sale/package', $body);
    }

    /**
     * POST /sale/sale-info — consultar información de venta por transactionId.
     * Llamar solo si existe registro local previo de la venta.
     */
    public function getSaleInfo(Environment $env, string $transactionId): array
    {
        $body = [
            'environment' => $env->getType(),
            'transactionId' => $transactionId,
        ];

        return $this->post($env, '/sale/sale-info', $body);
    }

    /**
     * POST /information/status — estado detallado de una venta.
     * Llamar solo si existe registro local previo de la venta.
     */
    public function getStatus(Environment $env, string $transactionId): InfoResult
    {
        $body = [
            'environment' => $env->getType(),
            'transactionId' => $transactionId,
        ];

        $raw = $this->rawPost($env, '/information/status', $body);

        return $this->serializer->deserialize($raw, InfoResult::class, 'json');
    }

    /**
     * POST /information/packages — catálogo de paquetes disponibles.
     */
    public function listPackages(Environment $env): array
    {
        return $this->post($env, '/information/packages', ['environment' => $env->getType()]);
    }

    /**
     * POST /information/balance — saldo disponible en CUP y USD.
     */
    public function getBalance(Environment $env): EtecsaBalanceDto
    {
        $data = $this->post($env, '/information/balance', ['environment' => $env->getType()]);

        return new EtecsaBalanceDto(
            cupAmount: (float) ($data['CupBalance'] ?? $data['cupBalance'] ?? $data['cup'] ?? 0.0),
            usdAmount: (float) ($data['UsdBalance'] ?? $data['usdBalance'] ?? $data['usd'] ?? 0.0),
            fetchedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * POST /information/nationalities — catálogo de nacionalidades.
     */
    public function listNationalities(Environment $env): array
    {
        return $this->post($env, '/information/nationalities', ['environment' => $env->getType()]);
    }

    /**
     * POST /information/provinces — catálogo de provincias.
     */
    public function listProvinces(Environment $env): array
    {
        return $this->post($env, '/information/provinces', ['environment' => $env->getType()]);
    }

    /**
     * POST /information/commercialOffices — oficinas comerciales de una provincia.
     */
    public function listCommercialOffices(Environment $env, ?int $provinceId = null): array
    {
        $body = ['environment' => $env->getType()];

        if ($provinceId !== null) {
            $body['provinceId'] = $provinceId;
        }

        return $this->post($env, '/information/commercialOffices', $body);
    }

    /**
     * POST /tur/check-phone — verifica si un número admite SIM Temporal TURISTA.
     */
    public function checkPhone(Environment $env, string $phoneNumber): array
    {
        return $this->post($env, '/tur/check-phone', [
            'environment' => $env->getType(),
            'phoneNumber' => $phoneNumber,
        ]);
    }

    /**
     * POST /tur/sale — encola la venta individual de SIM Temporal TURISTA.
     *
     * @param array<string, mixed>|null $client Datos del cliente (ClientInput)
     */
    public function sellTur(
        Environment $env,
        string $transactionId,
        string $phoneNumber,
        string $packageCode,
        ?array $client = null,
    ): array {
        $body = [
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
            'phoneNumber' => $phoneNumber,
            'packageCode' => $packageCode,
        ];

        if ($client !== null) {
            $body['client'] = $client;
        }

        return $this->post($env, '/tur/sale', $body);
    }

    /**
     * POST /tur/sale/batch — encola la venta por lotes de SIM Temporal TURISTA.
     *
     * @param array<int, array<string, mixed>> $clients Lista de TurClientInput
     */
    public function sellTurBatch(
        Environment $env,
        string $transactionId,
        string $packageCode,
        array $clients = [],
    ): array {
        return $this->post($env, '/tur/sale/batch', [
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
            'packageCode' => $packageCode,
            'clients' => $clients,
        ]);
    }

    /**
     * POST /tur/sale-info — estado de una venta individual TUR.
     */
    public function getTurSaleInfo(Environment $env, string $transactionId, ?string $orderId = null): array
    {
        $body = [
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
        ];

        if ($orderId !== null) {
            $body['orderId'] = $orderId;
        }

        return $this->post($env, '/tur/sale-info', $body);
    }

    /**
     * POST /tur/batch-info — estado global de un lote TUR.
     */
    public function getTurBatchInfo(Environment $env, string $transactionId, ?string $orderId = null): array
    {
        $body = [
            'transactionId' => $transactionId,
            'environment' => $env->getType(),
        ];

        if ($orderId !== null) {
            $body['orderId'] = $orderId;
        }

        return $this->post($env, '/tur/batch-info', $body);
    }

    // -------------------------------------------------------------------------

    private function post(Environment $env, string $path, array $body): array
    {
        return (array) json_decode($this->rawPost($env, $path, $body), true);
    }

    private function rawPost(Environment $env, string $path, array $body): string
    {
        $url = $env->getBasePath() . $path;
        $start = microtime(true);

        $apiKey = $this->sysConfigRepo->findCachedValue('api.' . strtolower($env->getType()) . '.communications.key', mustBeActive: true);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
        if ($apiKey !== null && $apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $this->serializer->serialize($body, 'json'),
            ]);

            $content = $response->getContent();

            $this->etecsaLogger->info('ETECSA gateway call', [
                'path' => $path,
                'env' => $env->getType(),
                'ms' => round((microtime(true) - $start) * 1000),
                'status' => $response->getStatusCode(),
            ]);

            return $content;
        } catch (ClientExceptionInterface $e) {
            $this->etecsaLogger->error('ETECSA client error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new MyCurrentException('ETECSA_CLIENT_ERROR', $e->getMessage(), $e->getCode() ?: 400);
        } catch (ServerExceptionInterface $e) {
            $this->etecsaLogger->error('ETECSA server error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new MyCurrentException('ETECSA_SERVER_ERROR', $e->getMessage(), 502);
        } catch (TransportExceptionInterface | RedirectionExceptionInterface $e) {
            $this->etecsaLogger->error('ETECSA transport error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new MyCurrentException('ETECSA_GATEWAY_TIMEOUT', $e->getMessage(), 503);
        }
    }
}
