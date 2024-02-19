<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPriceTable;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationProvinces;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
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

class TakeProductService extends CommonService
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
     * @throws \Exception
     */
    public function takeProduct(): void
    {
        $environments = $this->environment->findBy([
            'scope' => 'ET',
            'isActive' => true,
        ]);

        foreach ($environments as $key => $item) {
            $response = $this->httpClient->request(
                'POST',
                $item->getBasePath().'/information/packages',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->serialize([
                        'environment' => $item->getType(),
                    ], 'json', []),
                ]
            );

            $content = $response->getContent();
            $products = (object)$response->toArray();
            foreach ($products as $productItem) {
                $currentProduct = (object)$productItem;
                $product = new CommunicationProduct();
                $product->setPackageType($currentProduct->PackageType);
                $product->setEnvironment($item);
                $product->setPackageId($currentProduct->Id);
                $product->setDescription($currentProduct->Description);
                $product->setEnabled($currentProduct->Enabled);
                if (!is_null($currentProduct->InitialDate)) {
                    $product->setInitialDate(new \DateTimeImmutable($currentProduct->InitialDate));
                }
                if (!is_null($currentProduct->FinalDate)) {
                    $product->setEndDateAt(new \DateTimeImmutable($currentProduct->FinalDate));
                }
                $product->setPrice($currentProduct->Price);
                $product->setProductType($currentProduct->PackageType);
                $this->em->persist($product);
            }
        }

        $this->em->flush();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function takeOtherData(): void
    {
        $environments = $this->environment->findBy([
            'scope' => 'ET',
            'isActive' => true,
        ]);

        foreach ($environments as $key => $item) {
            $nationalitiesResponse = $this->httpClient->request(
                'POST',
                $item->getBasePath().'/information/nationalities',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->serialize([
                        'environment' => $item->getType(),
                    ], 'json', []),
                ]
            );

            $nationalitiesET = $nationalitiesResponse->toArray();
            if (count($nationalitiesET) > 0) {
                $info = $this->em->getRepository(CommunicationNationality::class)->deleteAll($item);
            }
            foreach ($nationalitiesET as $nationalityItemET) {
                $ntItem = (object)$nationalityItemET;

                $nationality = new CommunicationNationality();
                $nationality->setEnvironment($item);
                $nationality->setComId($ntItem->Id);
                $nationality->setName($ntItem->Name);
                $nationality->setCodeAlpha3($ntItem->Abbreviation);

                $this->em->persist($nationality);
            }

            $provincesResponse = $this->httpClient->request(
                'POST',
                $item->getBasePath().'/information/provinces',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->serialize([
                        'environment' => $item->getType(),
                    ], 'json', []),
                ]
            );

            $provincesET = $provincesResponse->toArray();
            foreach ($provincesET as $provinceItemET) {
                $prvItem = (object)$provinceItemET;
                if (property_exists($prvItem, 'Id') && !is_null($prvItem->Id)) {

                    $commercialOfficesResponse = $this->httpClient->request(
                        'POST',
                        $item->getBasePath().'/information/commercialOffices',
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ],
                            'body' => $this->serializer->serialize([
                                'environment' => $item->getType(),
                                'provinceId' => $prvItem->Id,
                            ], 'json', []),
                        ]
                    );

                    $provincesComm = $this->em->getRepository(CommunicationProvinces::class)->findOneBy([
                        'environment' => $item,
                        'comId' => $prvItem->Id,
                    ]);

                    $commercialOfficesET = $commercialOfficesResponse->toArray();
                    if (!is_null($provincesComm) && count($commercialOfficesET) > 0) {
                        $this->em->getRepository(CommunicationOffice::class)->deleteAll($item, $provincesComm->getId());
                        $this->em->remove($provincesComm);
                    }
                    $province = new CommunicationProvinces();
                    $province->setName($prvItem->Name);
                    $province->setComId($prvItem->Id);
                    $province->setEnvironment($item);

                    $this->em->persist($province);
                    foreach ($commercialOfficesET as $commercialOfficeItemET) {
                        $coItem = (object)$commercialOfficeItemET;

                        if (property_exists($coItem, 'Id') && !is_null($coItem->Id)) {
                            $comOffice = new CommunicationOffice();
                            $comOffice->setComId($coItem->Id);
                            $comOffice->setName($coItem->Name);
                            $comOffice->setEnvironment($item);
                            $comOffice->setProvince($province);
                            $comOffice->setIsActive(true);
                            $comOffice->setIsAirport(false);

                            $this->em->persist($comOffice);
                        }
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @throws \Exception
     */
    public function configurePackages(int $productId = 100, string $env = 'TEST'): void
    {
        $environments = $this->environment->findBy([
            'scope' => 'ET',
            'type' => $env,
            'isActive' => true,
        ]);

        foreach ($environments as $key => $envItem) {
            $accounts = $this->em->getRepository(Account::class)->findBy([
                'environment' => $envItem,
                'isActive' => true,
            ]);
            if (count($accounts) > 0) {
                foreach ($accounts as $account) {
                    if (!is_null($account)) {
                        $products = $this->em->getRepository(CommunicationProduct::class)->getProductsByPackageId(
                            $productId,
                            $envItem->getId()
                        );
                        if (count($products) > 0) {
                            foreach ($products as $prdItem) {
                                $prices = $this->em->getRepository(CommunicationPriceTable::class)->findBy([
                                    'client' => $account->getClient(),
                                    'productId' => $prdItem->getPackageId(),
                                ], [
                                    'amount' => 'ASC',
                                ]);
                                if (count($prices) > 0) {
                                    $this->em->getRepository(CommunicationPackage::class)->deleteAllPackageByClient(
                                        $account->getId(),
                                        $envItem->getId(),
                                        $prdItem->getPackageId()
                                    );
                                    foreach ($prices as $price) {
                                        $package = new CommunicationPackage();
                                        $package->setTenant($account);
                                        $package->setEnvironment($envItem);

                                        $package->setComPackageType($prdItem->getPackageType());
                                        $package->setComId($prdItem->getPackageId());
                                        $package->setCommunicationDescription($prdItem->getDescription());

                                        $package->setComPrice($price->getStartPrice());
                                        $package->setComCurrency($price->getRangePriceCurrency());

                                        $package->setCurrency($price->getCurrency());
                                        $package->setAmount($price->getAmount());

                                        $package->setIsOffer(
                                            $package->getComPackageType() === 'A' &&
                                            $prdItem->getPrice() === 0 &&
                                            $prdItem->getPackageId() !== 100
                                        );
                                        $package->setStartAt(
                                            $prdItem->getInitialDate()
                                        );
                                        $package->setEndDateAt(
                                            $prdItem->getEndDateAt()
                                        );

                                        $this->em->persist($package);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->em->flush();
    }
}
