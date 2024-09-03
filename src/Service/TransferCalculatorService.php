<?php

namespace App\Service;

use App\ApiResource\Calculator;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\Transfer;
use App\Enums\BalanceStateEnum;
use App\Enums\RebusStatusEnum;
use App\Repository\EnvAuthRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Util\RebusUtil;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransferCalculatorService extends CommonService
{
    public function __construct(
        EntityManagerInterface               $em,
        Security                             $security,
        ParameterBagInterface                $parameters,
        MailerInterface                      $mailer,
        LoggerInterface                      $logger,
        UserPasswordHasherInterface          $passwordHasher,
        EnvironmentRepository                $environmentRepository,
        SysConfigRepository                  $sysConfigRepo,
        SerializerInterface                  $serializer,
        private readonly HttpClientInterface $httpClient,
        private readonly AuthService         $authService,
        private readonly EnvAuthRepository   $authRepository)
    {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
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
                $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath() . "/api/Transactions/calculator";
                $tokenIn = 'Bearer ' . $accessToken->getTokenAuth();
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
                                    'propertyName' => 'rebuspay.tenant.account.' . strtolower($user->getEnvironmentName()) . '.value',
                                ])?->getPropertyValue(),
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

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    public function getTransferData(Transfer $transfer, Account $user): Transfer
    {
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
            $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath() . "/api/Transactions/" . $transfer->getRebusPayId();
            $tokenIn = 'Bearer ' . $accessToken->getTokenAuth();
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => $tokenIn,
                    ]
                ]
            );

            $info = (object)$response->toArray();
            $transfer->setStatusId($info->workflowStatus);
            $enumClass = RebusStatusEnum::class;
            $enumItem = null;
            if (enum_exists($enumClass)) {
                $enumItem = $enumClass::from($info->workflowStatus);
            }
            $statusName = RebusUtil::getRebusStatusName($enumItem);
            $transfer->setStatusName($statusName);
            if (!is_null($enumItem) && $enumItem === RebusStatusEnum::Rejected) {
                $balanceInfo = $this->em->getRepository(BalanceOperation::class)->getBalanceByTransferId($transfer->getId(), $user->getId());
                if (!is_null($balanceInfo)) {
                    $balanceInfo->setState(BalanceStateEnum::REVERSED->value);
                }
            }
            $this->em->flush();


            return $transfer;
        } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
            if ($ex->getCode() === 401) {
                $accessToken->setClosedAt(new \DateTimeImmutable('now'));
                $this->em->flush();

                return $this->getTransferData($transfer, $user);
            }
            throw $ex;
        }
    }

}
