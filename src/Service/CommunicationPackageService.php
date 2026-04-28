<?php

namespace App\Service;

use App\DTO\CreateClientPackageDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Service\CommonService;

class CommunicationPackageService extends CommonService
{
    /**
     * @param int $productId
     * @param int|null $tenant
     * @return CommunicationClientPackage[]
     */
    public function all(int $productId, ?int $tenant = null): array
    {
        $product = $this->em->getRepository(CommunicationProduct::class)->find($productId);
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $productList = $clientPackageRepo->getAllPackages($product?->getEnvironment()?->getType(), $tenant);
        if (count($productList) === 0) {
            $productList = $this->em->getRepository(CommunicationPricePackage::class)->findBy([], [
                'amount' => 'ASC'
            ]);
        }
        return $productList;
    }

    /**
     * @throws MyCurrentException
     */
    public function create(CreateClientPackageDto $dto): CommunicationClientPackage
    {
        $tenant = $this->em->getRepository(Account::class)->find($dto->getTenantId());
        if ($tenant === null) {
            throw new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found', 404);
        }

        $pricePackage = $this->em->getRepository(CommunicationPricePackage::class)->find($dto->getPriceClientPackageId());
        if ($pricePackage === null) {
            throw new MyCurrentException('PRICE_PACKAGE_NOT_FOUND', 'Price package not found', 404);
        }

        $cp = new CommunicationClientPackage();
        $cp->setTenant($tenant);
        $cp->setPriceClientPackage($pricePackage);

        if ($dto->getEnvironmentId() !== null) {
            $env = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
            if ($env === null) {
                throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
            }
            $cp->setEnvironment($env);
        }

        $dataInfo = $pricePackage->getDataInfo() ?? [];
        $cp->setName($dto->getName() ?? $pricePackage->getName() ?? '');
        $cp->setDescription($dto->getDescription() ?? $pricePackage->getDescription() ?? '');
        $cp->setAmount($dto->getAmount() ?? $pricePackage->getAmount());
        $cp->setCurrency($dto->getCurrency() ?? $pricePackage->getCurrency());
        $cp->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt() ?? 'now'));
        if ($dto->getActiveEndAt() !== null) {
            $cp->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
        }
        if ($dto->getKnowMore() !== null || $pricePackage->getKnowMore() !== null) {
            $cp->setKnowMore($dto->getKnowMore() ?? $pricePackage->getKnowMore());
        }
        $cp->setBenefits($dto->getBenefits()       ?? $dataInfo['benefits']    ?? []);
        $cp->setTags($dto->getTags()               ?? $dataInfo['tags']        ?? []);
        $cp->setService($dto->getService()         ?? $dataInfo['service']     ?? []);
        $cp->setDestination($dto->getDestination() ?? $dataInfo['destination'] ?? []);
        $cp->setValidity($dto->getValidity()       ?? $dataInfo['validity']    ?? []);

        $this->em->persist($cp);
        $this->em->flush();

        return $cp;
    }
}
