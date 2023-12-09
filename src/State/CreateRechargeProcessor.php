<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationRecharge;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
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
        private readonly HttpClientInterface $httpClient
    ) {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    /**
     * @inheritDoc
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();
        $orderId = null;
        $transactionId = null;
        $body = null;
        $bodyCheck = null;
        if ($data instanceof CommunicationRecharge && $user instanceof Account) {
            $package = $this->em->getRepository(CommunicationPackage::class)->find($data->getPackageId());
            $data->setPackage($package);
            $lastSequence = $this->em->getRepository(CommunicationRecharge::class)->getSequence()?->getSequence();
            $data->setSequence(is_null($lastSequence) ? 1 : $lastSequence + 1);
            $data->setAmount($data->getAmount() ?? $package?->getAmount());
            $data->setCurrency($data->getCurrency() ?? $package?->getCurrency());
            $data->setPrice($data->getAmount() * 24);
            $data->setRate(24);
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

                $content = $response->getContent();
                $info = (object)$response->toArray();
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
                $contentCheck = $responseStatus->getContent();
                $infoCheck = (object)$responseStatus->toArray();
                $data->setComInfo([
                    'info' => [
                        'sale' => $infoCheck->sale,
                        'result' => $infoCheck->result,
                        'transactionID' => $transactionId,
                    ],
                ]);
                $data->setStatus(((object)$infoCheck->sale)->state);
            } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
                $data->setStatus('FAILED');
                $data->setComInfo([
                    'error' => sprintf(
                        "action=Recharge, Message=%s, Type=%s",
                        $ex->getMessage(),
                        $ex->getTraceAsString()
                    ),
                    'orderID' => $orderId,
                    'transactionID' => $transactionId,
                    'body' => $body,
                    'bodyCheck' => $bodyCheck,
                    'errorTrace' => $ex->getTrace()
                ]);
            }
            $this->em->persist($data);
            $this->em->flush();
        }

        return $data;
    }
}
