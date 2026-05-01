<?php

namespace App\Service;

use App\DTO\CreateClientPackageDto;
use App\DTO\CreatePricePackageDto;
use App\DTO\UpdateClientPackageDto;
use App\DTO\UpdatePricePackageDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPrice;
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

        $existing = $this->em->getRepository(CommunicationClientPackage::class)->findOneBy([
            'tenant' => $tenant,
            'priceClientPackage' => $pricePackage,
        ]);
        if ($existing !== null) {
            throw new MyCurrentException('DUPLICATE_CLIENT_PACKAGE', 'Client package already exists for this tenant and price package', 409);
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
        } elseif ($pricePackage->getEnvironment() !== null) {
            $cp->setEnvironment($pricePackage->getEnvironment());
        }

        $dataInfo = $pricePackage->getDataInfo();
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

    /** @throws MyCurrentException */
    public function createPrice(CreatePricePackageDto $dto): CommunicationPricePackage
    {
        $account = $this->em->getRepository(Account::class)->find($dto->getTenantId());
        if ($account === null) {
            throw new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found', 404);
        }

        $product = $this->em->getRepository(CommunicationProduct::class)->find($dto->getProductId());
        if ($product === null) {
            throw new MyCurrentException('PRODUCT_NOT_FOUND', 'Product not found', 404);
        }

        if ($dto->getEnvironmentId() === null && !$account->isActive()) {
            throw new MyCurrentException('TENANT_INACTIVE', 'Tenant is inactive', 422);
        }

        $pp = new CommunicationPricePackage();
        $pp->setTenant($account);
        $pp->setProduct($product);
        $pp->setPrice($dto->getPrice());
        $pp->setPriceCurrency($dto->getPriceCurrency());
        $pp->setAmount($dto->getAmount());
        $pp->setCurrency($dto->getCurrency());
        $pp->setName(mb_substr($dto->getName() ?? "Cubacel {$dto->getPrice()} {$dto->getPriceCurrency()}", 0, 255));
        $pp->setIsActive($dto->getIsActive() ?? true);
        $pp->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt() ?? 'now'));

        if ($dto->getActiveEndAt() !== null) {
            $pp->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
        }
        if ($dto->getDescription() !== null) {
            $pp->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getPriceUsedId() !== null) {
            $priceUsed = $this->em->getRepository(CommunicationPrice::class)->find($dto->getPriceUsedId());
            if ($priceUsed !== null) {
                $pp->setPriceUsed($priceUsed);
            }
        }
        if ($dto->getEnvironmentId() !== null) {
            $env = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
            if ($env === null) {
                throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
            }
            $pp->setEnvironment($env);
        }

        $this->em->persist($pp);
        $this->em->flush();

        return $pp;
    }

    public function updatePrice(CommunicationPricePackage $pp, UpdatePricePackageDto $dto): CommunicationPricePackage
    {
        if ($dto->getPrice() !== null) {
            $pp->setPrice($dto->getPrice());
        }
        if ($dto->getPriceCurrency() !== null) {
            $pp->setPriceCurrency($dto->getPriceCurrency());
        }
        if ($dto->getAmount() !== null) {
            $pp->setAmount($dto->getAmount());
        }
        if ($dto->getCurrency() !== null) {
            $pp->setCurrency($dto->getCurrency());
        }
        if ($dto->getName() !== null) {
            $pp->setName(mb_substr($dto->getName(), 0, 255));
        }
        if ($dto->getDescription() !== null) {
            $pp->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getIsActive() !== null) {
            $pp->setIsActive($dto->getIsActive());
        }
        if ($dto->getActiveStartAt() !== null) {
            $pp->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt()));
        }
        if ($dto->getActiveEndAt() !== null) {
            $pp->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
        }

        $this->em->flush();

        return $pp;
    }

    public function togglePrice(CommunicationPricePackage $pp): CommunicationPricePackage
    {
        $pp->setIsActive(!$pp->isActive());
        $this->em->flush();

        return $pp;
    }

    public function deletePrice(CommunicationPricePackage $pp): void
    {
        $this->em->remove($pp);
        $this->em->flush();
    }

    public function updateClientPackage(CommunicationClientPackage $cp, UpdateClientPackageDto $dto): CommunicationClientPackage
    {
        if ($dto->getName() !== null) {
            $cp->setName(mb_substr($dto->getName(), 0, 255));
        }
        if ($dto->getDescription() !== null) {
            $cp->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getAmount() !== null) {
            $cp->setAmount($dto->getAmount());
        }
        if ($dto->getCurrency() !== null) {
            $cp->setCurrency($dto->getCurrency());
        }
        if ($dto->getActiveStartAt() !== null) {
            $cp->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt()));
        }
        if ($dto->getActiveEndAt() !== null) {
            $cp->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
        }
        if ($dto->getKnowMore() !== null) {
            $cp->setKnowMore(mb_substr($dto->getKnowMore(), 0, 500));
        }
        if ($dto->getBenefits() !== null) {
            $cp->setBenefits($dto->getBenefits());
        }
        if ($dto->getTags() !== null) {
            $cp->setTags($dto->getTags());
        }
        if ($dto->getService() !== null) {
            $cp->setService($dto->getService());
        }
        if ($dto->getDestination() !== null) {
            $cp->setDestination($dto->getDestination());
        }
        if ($dto->getValidity() !== null) {
            $cp->setValidity($dto->getValidity());
        }
        if ($cp->getEnvironment() === null && $cp->getPriceClientPackage()?->getEnvironment() !== null) {
            $cp->setEnvironment($cp->getPriceClientPackage()->getEnvironment());
        }

        $this->em->flush();

        return $cp;
    }

    public function deleteClientPackage(CommunicationClientPackage $cp): void
    {
        $this->em->remove($cp);
        $this->em->flush();
    }
}
