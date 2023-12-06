<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\CreateSaleDto;
use App\Entity\Account;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSale;
use App\Repository\CommunicationPackageRepository;
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

class CreateSaleProcessor implements ProcessorInterface
{
    private Serializer $serializer;
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient
    )
    {
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
        if ($data instanceof CreateSaleDto && $user instanceof Account) {
            $sale = new CommunicationSale();
            $sale->setTenant($user);
            $sale->setType('02');
            $sale->setPackageId($data->packageId);
            $package = $this->em->find(CommunicationPackage::class, $data->packageId);
            $lastSequence = $this->em->getRepository(CommunicationSale::class)->getSequence()?->getSequenceInfo();
            $sale->setPackage($package);
            $sale->setAmount($package?->getAmount());
            $sale->setCurrency($package?->getCurrency());
            if (is_null($lastSequence)) {
                $sale->setSequenceInfo(1);
            } else {
                $sale->setSequenceInfo($lastSequence + 1);
            }

            $commercialOffice = $this->em->getRepository(CommunicationOffice::class)->find($data->client->commercialOfficeId);
            $nationality = $this->em->getRepository(CommunicationNationality::class)->find($data->client->nationality);

            try {
                $url = $user?->getEnvironment()?->getBasePath().'/sale/package';
                $body  = $this->serializer->serialize(
                    [
                        'client' => [
                            'id' => $data->client->identification,
                            'name' => $data->client->name,
                            'identificationType' => 9,
                            'arrivalDate' => $data->client->arrivalDateAt,
                            'isAirport' => $commercialOffice?->isIsAirport(),
                            'commercialOfficeId' => $commercialOffice?->getComId(),
                            'provinceId' => $commercialOffice?->getProvince()?->getComId(),
                            'nationality' => $nationality?->getComId()
                        ],
                        'packageInfo' => [
                            'id' => $package?->getComId(),
                            'packageType' => $package?->getComPackageType()
                        ],
                        'transactionId' => (new \DateTime('now'))->format('ymd').'02'.str_pad(
                                $sale->getSequenceInfo(),
                                5,
                                '0',
                                STR_PAD_LEFT
                            ),
                        'environment' => $user?->getEnvironment()?->getType()
                    ], 'json', []
                );
                $response = $this->httpClient->request(
                    'POST',
                    $url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ],
                        'body' => $body,
                    ]
                );

                $content = $response->getContent();
                $info = (object) $response->toArray();
                $fullResponse = (object) $info->fullResponse;
                $serverSale = (object) $info->sale;

                $sale->setStatus($serverSale->state);
                $sale->setClientInfo([
                    'info' => [
                        'sale' => $info->sale,
                        'result' => $info->result,
                        'code' => ((object) $fullResponse->Sale)->Code
                    ]
                ]);
            } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface $ex) {
                $sale->setStatus('FAILED');
                $sale->setClientInfo([
                    'error' => $ex
                ]);
            }

            $this->em->persist($sale);
            $this->em->flush();

            return $sale;
        }
        return $data;
    }
}
