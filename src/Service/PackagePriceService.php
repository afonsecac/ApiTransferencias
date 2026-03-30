<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Service\CommonService;

class PackagePriceService extends CommonService
{
    /**
     * @param int $productId
     * @param int|null $clientId
     * @return array
     */
    public function getUnusedPrices(int $productId, int $clientId = null): array
    {
        /** @var \App\Repository\CommunicationPricePackageRepository $pricePackageRepo */
        $pricePackageRepo = $this->em->getRepository(CommunicationPricePackage::class);
        $ids = $pricePackageRepo->getIdsWithPrices($productId, $clientId);

        /** @var \App\Repository\CommunicationPriceRepository $priceRepo */
        $priceRepo = $this->em->getRepository(CommunicationPrice::class);
        return $priceRepo->getPricesNoUsed($ids);
    }

    /**
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function addPackagePrices(array $data): array
    {
        $isInserted = false;
        $isCompleted = false;
        $insertedCount = 0;
        foreach ($data as $packageItemArray) {
            $packageItem = (object) $packageItemArray;
            $packagePrice = new CommunicationPricePackage();
            $packagePrice->setPrice($packageItem->price);
            $packagePrice->setPriceCurrency($packageItem->priceCurrency);
            $packagePrice->setDescription($packageItem->description);
            $packagePrice->setName($packageItem->name);
            $packagePrice->setDataInfo($packageItem->dataInfo);
            $packagePrice->setAmount($packageItem->amount);
            $packagePrice->setCurrency($packageItem->currency);
            $packagePrice->setActiveStartAt(new \DateTimeImmutable($packageItem->activeStartAt));
            if (!is_null($packageItem->activeEndAt)) {
                $packagePrice->setActiveEndAt(new \DateTimeImmutable($packageItem->activeEndAt));
            }
            $packagePrice->setIsActive($packageItem->isActive);
            $packagePrice->setKnowMore($packageItem->knowMore);
            $priceUsed = $this->em->getRepository(CommunicationPrice::class)->find($packageItem->priceId);
            $packagePrice->setPriceUsed($priceUsed);
            $product = $this->em->getRepository(CommunicationProduct::class)->find($packageItem->productId);
            $packagePrice->setProduct($product);
            if (!is_null($packageItem->tenantId)) {
                $tenant = $this->em->getRepository(Account::class)->find($packageItem->tenantId);
                $packagePrice->setTenant($tenant);
                $this->em->persist($packagePrice);
                if (!is_null($tenant)) {
                    $this->createPackageClient($packagePrice, $tenant);
                }
                $insertedCount++;
            } else if (!is_null($packageItem->tenantIds) && is_array($packageItem->tenantIds) && count($packageItem->tenantIds) > 0) {
                foreach ($packageItem->tenantIds as $tenantId) {
                    $tenant = $this->em->getRepository(Account::class)->find($tenantId);
                    $this->copyPricePackage($packagePrice, $tenant);
                    $insertedCount++;
                }
            } else {
                $this->em->persist($packagePrice);
                $insertedCount++;
            }

            $isInserted = true;

        }
        if ($isInserted) {
            $this->em->flush();
            $isCompleted = true;
        }

        return [
            'isInserted' => $isCompleted,
            'items' => $insertedCount,
        ];
    }

    public function createPackageClient(CommunicationPricePackage $pricePackage, Account $tenant): void
    {
        $clientPackage = new CommunicationClientPackage();
        $clientPackage->setTenant($tenant);
        $clientPackage->setPriceClientPackage($pricePackage);
        $dataInfo = (object) $pricePackage->getDataInfo();
        $clientPackage->setBenefits($dataInfo->benefits);
        $clientPackage->setDestination($dataInfo->destination);
        $clientPackage->setService($dataInfo->service);
        $clientPackage->setTags($dataInfo->tags);
        $clientPackage->setValidity($dataInfo->validity);
        $clientPackage->setName($pricePackage->getName());
        $clientPackage->setAmount($pricePackage->getAmount());
        $clientPackage->setCurrency($pricePackage->getCurrency());
        $clientPackage->setDescription($pricePackage->getDescription());
        $clientPackage->setActiveStartAt($pricePackage->getActiveStartAt());
        $clientPackage->setActiveEndAt($pricePackage->getActiveEndAt());
        $clientPackage->setKnowMore($pricePackage->getKnowMore());
        $this->em->persist($clientPackage);
    }

    public function copyPricePackage(CommunicationPricePackage $pricePackage, Account $tenant): void
    {
        $copyPricePackage = new CommunicationPricePackage();
        $copyPricePackage->setTenant($tenant);
        $copyPricePackage->setPrice($pricePackage->getPrice());
        $copyPricePackage->setPriceCurrency($pricePackage->getPriceCurrency());
        $copyPricePackage->setDescription($pricePackage->getDescription());
        $copyPricePackage->setName($pricePackage->getName());
        $copyPricePackage->setDataInfo($pricePackage->getDataInfo());
        $copyPricePackage->setAmount($pricePackage->getAmount());
        $copyPricePackage->setCurrency($pricePackage->getCurrency());
        $copyPricePackage->setActiveStartAt($pricePackage->getActiveStartAt());
        $copyPricePackage->setActiveEndAt($pricePackage->getActiveEndAt());
        $copyPricePackage->setIsActive($pricePackage->isIsActive());
        $copyPricePackage->setKnowMore($pricePackage->getKnowMore());
        $copyPricePackage->setPriceUsed($pricePackage->getPriceUsed());
        $copyPricePackage->setProduct($pricePackage->getProduct());
        $this->em->persist($copyPricePackage);
        $this->createPackageClient($copyPricePackage, $tenant);
    }
}
