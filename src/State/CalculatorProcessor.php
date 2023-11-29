<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Calculator;
use App\Entity\Account;
use App\Repository\EnvAuthRepository;
use App\Repository\SysConfigRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CalculatorProcessor implements ProcessorInterface
{
    private Serializer $serializer;

    public function __construct(
        private readonly AuthService $authService,
        private readonly EnvAuthRepository $authRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $configRepository,
        private readonly Security $security
    ) {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();
        $accessToken = null;
        $accessTokens = $this->authRepository->findBy([
            'closedAt' => null,
            'permission' => $user,
        ], [
            'createdAt' => 'DESC',
        ]);
        if (is_null($accessTokens) || count($accessTokens) === 0) {
            $token = $this->authService->start();
            $accessToken = $this->authRepository->findOneBy([
                'tokenAuth' => $token,
            ]);
        } else {
            $accessToken = $accessTokens[0];
            $token = $accessToken->getTokenAuth();
        }
        try {
            if ($user instanceof Account) {
                $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath()."/api/Transactions/calculator";
                $tokenIn = 'Bearer '.$accessToken->getTokenAuth();
                if ($data instanceof Calculator) {
                    $response = $this->httpClient->request(
                        'POST',
                        $url,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                                'Authorization' => $tokenIn,
                            ],
                            'body' => $this->serializer->serialize(
                                [
                                    'fromCurrency' => $data->getSendCurrency(),
                                    'toCurrency' => $data->getSendCurrency(),
                                    'sendAmount' => $data->getSendAmount(),
                                    'tenantProcessorId' => (int)$this->configRepository->findOneBy([
                                        'propertyName' => 'rebuspay.tenant.account.'.strtolower($user->getEnvironmentName()).'.value'
                                ])?->getPropertyValue()
                                ], 'json', []
                            ),
                        ]
                    );

                    $content = $response->getContent();

                    $info = (object)$response->toArray();

                    $data->setRate($info->rate);
                    $data->setFeeAmount($info->feeAmount);
                    $data->setTotalCurrency($data->getSendCurrency());
                    $data->setToCurrency($data->getSendCurrency());
                    $data->setTotal($info->totalAmount);
                    $data->setReceiveAmount($info->receiveAmount);
                }
            }
        } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
            if ($ex->getCode() === 401) {
                $accessToken->setClosedAt(new \DateTimeImmutable('now'));
                $this->em->flush();


                return $this->process($data, $operation, $uriVariables, $context);
            }
            throw $ex;
        }

        return $data;
    }
}
