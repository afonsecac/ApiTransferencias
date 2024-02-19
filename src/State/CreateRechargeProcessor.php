<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationRecharge;
use App\Exception\MyCurrentException;
use App\Repository\BalanceOperationRepository;
use App\Service\ConfigureSequenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
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

class CreateRechargeProcessor implements ProcessorInterface
{

    private Serializer $serializer;

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly BalanceOperationRepository $balanceRepository,
        private readonly ConfigureSequenceService $configureSequence,
    ) {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    /**
     * @inheritDoc
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return CommunicationRecharge|mixed
     * @throws MyCurrentException
     * @throws DecodingExceptionInterface
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): CommunicationRecharge|null {
        $user = $this->security->getUser();
        $orderId = null;
        $transactionId = null;
        $body = null;
        $bodyCheck = null;
        $balanceOperation = null;
        if ($data instanceof CommunicationRecharge && $user instanceof Account) {
            $balance = $this->balanceRepository->getBalanceOutput($user->getId());
            $package = $this->em->getRepository(CommunicationPackage::class)->getPackageById(
                $data->getPackageId(),
                $user
            );
            if (!is_null($package)) {
                if ($balance < $package->getAmount()) {
                    throw new MyCurrentException('COM001', 'Insufficient balance');
                }
                $lastSequence = $this->configureSequence->getLastSequence(CommunicationRecharge::class);
                $data->setPackage($package);
                $data->setSequence($lastSequence);
                $data->setAmount($data->getAmount() ?? $package?->getAmount());
                $data->setCurrency($data->getCurrency() ?? $package?->getCurrency());
                $data->setPrice($package?->getComPrice());
                $data->setRate(($package->getComPrice() / $package->getAmount()));
                $data->setTenant($user);
                try {
                    $urlRecharge = $user?->getEnvironment()?->getBasePath().'/sale/recharge';
                    $urlStatus = $user?->getEnvironment()?->getBasePath().'/sale/sale-info';
                    $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                            $data->getSequence(),
                            5,
                            '0',
                            STR_PAD_LEFT
                        );
                    $body = [
                        'phoneNumber' => $data->getPhoneNumber(),
                        'productCode' => $package?->getComId(),
                        'productPrice' => round($data?->getPrice(), 2),
                        'transactionId' => $transactionId,
                        'environment' => $user?->getEnvironment()?->getType(),
                    ];
                    $response = $this->httpClient->request(
                        'POST',
                        $urlRecharge,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ],
                            'body' => $this->serializer->serialize($body, 'json', []),
                        ]
                    );
                    $info = json_decode(
                        $response->getContent(),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                    $orderId = $info->orderId;

                    $data->setStatus('PENDING');

                    $bodyCheck = [
                        'orderId' => $orderId,
                        'transactionId' => $transactionId,
                        'environment' => $user?->getEnvironment()?->getType(),
                    ];

                    $responseStatus = $this->httpClient->request(
                        'POST',
                        $urlStatus,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ],
                            'body' => $this->serializer->serialize($bodyCheck, 'json', []),
                            'timeout' => 15 * 60 * 60,
                        ]
                    );
                    $infoCheck = json_decode(
                        $responseStatus->getContent(),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                    $data->setComInfo([
                        'info' => [
                            'sale' => $infoCheck->sale,
                            'result' => $infoCheck->result,
                            'transactionID' => $transactionId,
                        ],
                    ]);
                    $data->setStatus(((object)$infoCheck->sale)->state);
                    $balanceOperation = new BalanceOperation();
                    $balanceOperation->setTenant($user);
                    $balanceOperation->setAmount($package->getAmount());
                    $balanceOperation->setCurrency($package->getCurrency());
                    $balanceOperation->setState('PENDING');
                    $balanceOperation->setOperationType('DEBIT');
                    $total = $package->getAmount() + $balanceOperation->getAmountTax() - $balanceOperation->getDiscount(
                        );
                    $balanceOperation->setTotalAmount(-1 * $total);
                    $balanceOperation->setTotalCurrency($package->getCurrency());
                    $this->em->persist($balanceOperation);

                } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
                    $data->setStatus('FAILED');
                    $comInfo = [
                        'error' => sprintf(
                            "action=Recharge, Message=%s",
                            $ex->getMessage()
                        ),
                        'orderID' => $orderId,
                        'transactionID' => $transactionId,
                        'body' => $body,
                        'bodyCheck' => $bodyCheck,
                    ];
                    if ($ex instanceof ClientExceptionInterface) {
                        try {
                            $streamContent = fopen($ex->getResponse()->getContent(), 'r');
                            $meta = stream_get_meta_data($streamContent);
                            $info = $meta;
                        } catch (ClientExceptionInterface $e) {
                        } catch (RedirectionExceptionInterface $e) {
                        } catch (ServerExceptionInterface $e) {
                        } catch (TransportExceptionInterface $e) {
                        }
                        $comInfo['code'] = 'COM004';
                        $comInfo['error'] = 'The provider server no response';
                    }
                    $data->setComInfo($comInfo);
                } finally {
                    $this->em->persist($data);
                    if (!is_null($balanceOperation)) {
                        $balanceOperation->setRechargeId($data->getId());
                        $balanceOperation->setRecharge($data);
                    }
                    $this->em->flush();
                }
            } else {
                throw new MyCurrentException('COM003', 'The package don\'t exist');
            }
        }

        return $data;
    }
}
