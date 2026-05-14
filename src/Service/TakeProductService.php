<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPriceTable;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Etecsa\EtecsaCatalogSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @deprecated Usar EtecsaCatalogSyncService directamente.
 * Los métodos takeProduct() y takeOtherData() delegan en EtecsaCatalogSyncService.
 * Este servicio se mantiene para compatibilidad con app:takeOther:command y app:takeProduct.
 */
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
        SerializerInterface $serializer,
        private readonly EtecsaCatalogSyncService $catalogSyncService,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }


    /**
     * @deprecated Usar EtecsaCatalogSyncService::syncProducts()
     */
    public function takeProduct(string $env): array
    {
        try {
            $environments = $this->environmentRepository->findBy([
                'scope' => 'ET',
                'isActive' => true,
                'type' => $env,
            ]);

            $items = 0;
            foreach ($environments as $item) {
                $result = $this->catalogSyncService->syncProducts($item);
                $items += $result->created;
            }

            return ['items' => $items, 'isProcessed' => true];
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $codeExc = $exception->getCode() ? (string) $exception->getCode() : 'Unknown error';
            throw new MyCurrentException($codeExc, $exception->getMessage());
        }
    }

    /**
     * @deprecated Usar EtecsaCatalogSyncService::syncNationalities() / syncProvinces() / syncOffices()
     */
    public function takeOtherData(): void
    {
        $environments = $this->environmentRepository->findBy([
            'scope' => 'ET',
            'isActive' => true,
        ]);

        foreach ($environments as $item) {
            $this->catalogSyncService->syncNationalities($item);
            $this->catalogSyncService->syncProvinces($item);
            $this->catalogSyncService->syncOffices($item);
        }
    }

    /**
     * @throws \Exception
     */
    public function configurePackages(int $productId = 100, string $env = 'TEST'): void
    {
        $environments = $this->environmentRepository->findBy([
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
