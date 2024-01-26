<?php

namespace App\Service;

use App\ApiResource\Calculator;
use App\Entity\Account;
use App\Repository\EnvAuthRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommonService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransferCalculatorService extends CommonService
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
        private readonly HttpClientInterface $httpClient,
        private readonly AuthService $authService,
        private readonly EnvAuthRepository $authRepository,
    ) {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo
        );
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function calculator(Calculator $data): Calculator
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
                                'tenantProcessorId' => (int)$this->sysConfigRepo->findOneBy([
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
        } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
            if ($ex->getCode() === 401) {
                $accessToken->setClosedAt(new \DateTimeImmutable('now'));
                $this->em->flush();


                return $this->calculator($data);
            }
            throw $ex;
        }

        return $data;
    }

}
