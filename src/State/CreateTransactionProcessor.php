<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Repository\BankCardRepository;
use App\Repository\EnvAuthRepository;
use App\Repository\SenderRepository;
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

class CreateTransactionProcessor implements ProcessorInterface
{
    private Serializer $serializer;

    public function __construct(
        private readonly AuthService $authService,
        private readonly EnvAuthRepository $authRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $configRepository,
        private readonly Security $security,
        private readonly BankCardRepository $bankCardRepository,
        private readonly SenderRepository $senderRepository,
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
                $url = $accessToken->getPermission()?->getEnvironment()?->getBasePath()."/api/Transactions";
                $tokenIn = 'Bearer '.$accessToken->getTokenAuth();
                if ($data instanceof Transfer && $data->getTransactionType() !== '5') {
                    $beneficiary = $this->bankCardRepository->find($data->getBeneficiaryId());
                    $sender = $this->senderRepository->find($data->getSenderId());
                    $serializerInfo = $this->serializer->serialize(
                        [
                            'accountId' => $user->getAccountId(),
                            'currency' => $data->getCurrency(),
                            'amount' => $data->getAmountDeposit(),
                            'transactionType' => (int)$data->getTransactionType(),
                            'reason' => $data->getReasonNote(),
                            'senderId' => $sender?->getRebusSenderId(),
                            'beneficiaryId' => $beneficiary?->getRebusId(),
                            'tenantProcessorId' => (int)$this->configRepository->findOneBy([
                                'propertyName' => 'rebuspay.tenant.account.'.strtolower(
                                        $user->getEnvironmentName()
                                    ).'.value',
                                'isActive' => true,
                            ])?->getPropertyValue(),
                        ], 'json', []
                    );
                    $response = $this->httpClient->request(
                        'POST',
                        $url,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                                'Authorization' => $tokenIn,
                            ],
                            'body' => $serializerInfo,
                        ]
                    );

                    $content = $response->getContent();
                    $info = (object)$response->toArray();

                    $getInfoResponse = $this->httpClient->request(
                        'GET',
                        $url."/".$info->id,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                                'Authorization' => $tokenIn,
                            ],
                        ]
                    );
                    $getInfoContent = $getInfoResponse->getContent();
                    $getInfo = (object) $getInfoResponse->toArray();

                    $data->setTotalAmount($getInfo->transAmount);
                    $data->setCurrencyTotal($data->getCurrency());
                    $data->setRateToChange($getInfo->exchangeRate);
                    $data->setRebusPayId($info->id);
                    $data->setBeneficiary($beneficiary);
                    $data->setTenant($user);
                    $data->setAmountCommission($getInfo->feeTransAmount);
                    $data->setCurrencyCommission($data->getCurrency());
                    $data->setStatusId($getInfo->workflowStatus);
                    switch ($getInfo->workflowStatus) {
                        case 1:
                            $data->setSenderName('IN_DEPOSIT');
                            break;
                        case 2:
                            $data->setStatusName('DEPOSIT');
                            break;
                        default:
                            $data->setStatusName('PENDING');
                            break;
                    }
                    $data->setSender($sender);
                    $data->setSenderName(
                        sprintf(
                            "%s%s %s",
                            $sender?->getFirstName(),
                            is_null($sender?->getMiddleName()) ? "" : " ".$sender?->getMiddleName(),
                            $sender?->getLastName()
                        )
                    );
                    $data->setBeneficiaryName(
                        sprintf(
                            "%s%s %s",
                            $beneficiary?->getBeneficiary()?->getFirstName(),
                            is_null(
                                $beneficiary?->getBeneficiary()?->getMiddleName()
                            ) ? "" : " ".$beneficiary?->getBeneficiary()?->getMiddleName(),
                            $beneficiary?->getBeneficiary()?->getLastName()
                        )
                    );
                }

                $this->em->persist($data);
                $this->em->flush();
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
