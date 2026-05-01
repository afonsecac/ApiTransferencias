<?php

namespace App\Service;

use App\DTO\CreateAdminPromotionDto;
use App\DTO\UpdatePromotionDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use Psr\Log\LoggerInterface;

class CommunicationPromotionService extends CommonService
{
    /**
     * Crea los CommunicationClientPackage y CommunicationPricePackage
     * asociados a una promoción a partir del rango de precios.
     *
     * @param CommunicationPromotions $promotion La promoción creada
     * @param array $packageData Datos: currency, amountFrom, amountTo, amountStep, clients (opcional)
     */
    public function createPackagesForPromotion(CommunicationPromotions $promotion, array $packageData): int
    {
        $currency = $packageData['currency'] ?? 'CUP';
        $amountFrom = (float) ($packageData['amountFrom'] ?? 0);
        $amountTo = (float) ($packageData['amountTo'] ?? 0);
        $amountStep = (int) ($packageData['amountStep'] ?? 25);
        $clientIds = $packageData['clients'] ?? [];
        $environment = $promotion->getEnvironment();
        $product = $promotion->getProduct();

        if ($amountFrom <= 0 || $amountTo <= 0 || $amountStep <= 0 || $environment === null || $product === null) {
            return 0;
        }

        // 1. Buscar precios existentes en el rango
        $existingPrices = $this->em->getRepository(CommunicationPrice::class)->createQueryBuilder('p')
            ->where('p.currencyPrice = :currency')
            ->andWhere('p.startPrice >= :from')
            ->andWhere('p.startPrice <= :to')
            ->andWhere('p.isActive = :active')
            ->setParameter('currency', $currency)
            ->setParameter('from', $amountFrom)
            ->setParameter('to', $amountTo)
            ->setParameter('active', true)
            ->orderBy('p.startPrice', 'ASC')
            ->getQuery()
            ->getResult();

        // Indexar precios existentes por su monto
        $pricesByAmount = [];
        foreach ($existingPrices as $price) {
            $pricesByAmount[(int) $price->getStartPrice()] = $price;
        }

        // Calcular la mejor tasa para el sistema (max USD/CUP) como referencia para precios nuevos
        $referenceRate = 0;
        foreach ($existingPrices as $price) {
            if ($price->getStartPrice() > 0) {
                $rate = $price->getAmount() / $price->getStartPrice();
                if ($rate > $referenceRate) {
                    $referenceRate = $rate;
                }
            }
        }

        // Generar todos los montos del rango con el step
        $filteredPrices = [];
        for ($amount = (int) $amountFrom; $amount <= (int) $amountTo; $amount += $amountStep) {
            if (isset($pricesByAmount[$amount])) {
                $filteredPrices[] = $pricesByAmount[$amount];
            } elseif ($referenceRate > 0) {
                // Crear precio nuevo usando la tasa de referencia
                $usdAmount = round($amount * $referenceRate, 2);
                $newPrice = new CommunicationPrice();
                $newPrice->setStartPrice($amount);
                $newPrice->setCurrencyPrice($currency);
                $newPrice->setAmount($usdAmount);
                $newPrice->setCurrency($existingPrices[0]?->getCurrency() ?? 'USD');
                $newPrice->setIsActive(true);
                $newPrice->setValidStartAt($promotion->getStartAt());
                $newPrice->setValidEndAt($promotion->getEndAt());
                $this->em->persist($newPrice);
                $filteredPrices[] = $newPrice;
                $this->logger->info("Created new price: {$amount} {$currency} = {$usdAmount} USD (rate: {$referenceRate})");
            }
        }

        if (empty($filteredPrices)) {
            return 0;
        }

        // Flush para asignar IDs a los precios nuevos
        $this->em->flush();

        // 2. Obtener las cuentas (tenants) del environment
        $accountCriteria = [
            'environment' => $environment,
            'isActive' => true,
        ];
        $accounts = $this->em->getRepository(Account::class)->findBy($accountCriteria);

        // Si se pasan client IDs, filtrar solo esas cuentas
        if (!empty($clientIds)) {
            $accounts = array_filter($accounts, function (Account $account) use ($clientIds) {
                return in_array($account->getClient()?->getId(), $clientIds, true);
            });
        }

        if (empty($accounts)) {
            return 0;
        }

        $packagesCreated = 0;

        // 3. Por cada precio × cada account → crear PricePackage + ClientPackage
        foreach ($filteredPrices as $price) {
            foreach ($accounts as $account) {
                // Crear CommunicationPricePackage
                $pricePackage = new CommunicationPricePackage();
                $pricePackage->setProduct($product);
                $pricePackage->setPriceUsed($price);
                $pricePackage->setPrice($price->getStartPrice());
                $pricePackage->setPriceCurrency($currency);
                $pricePackage->setAmount($price->getAmount());
                $pricePackage->setCurrency($price->getCurrency());
                $pricePackage->setTenant($account);
                $pricePackage->setEnvironment($environment);
                $pricePackage->setIsActive(true);
                $pricePackage->setActiveStartAt($promotion->getStartAt());
                $pricePackage->setActiveEndAt($promotion->getEndAt());
                $pricePackage->setName(sprintf('Cubacel %d %s', (int) $price->getStartPrice(), $currency));
                $this->em->persist($pricePackage);

                // Crear CommunicationClientPackage
                $clientPackage = new CommunicationClientPackage();
                $clientPackage->setTenant($account);
                $clientPackage->setPriceClientPackage($pricePackage);
                $clientPackage->setName($pricePackage->getName());
                $clientPackage->setDescription($pricePackage->getName());
                $clientPackage->setAmount($price->getAmount());
                $clientPackage->setCurrency($price->getCurrency());
                $clientPackage->setActiveStartAt($promotion->getStartAt());
                $clientPackage->setActiveEndAt($promotion->getEndAt());
                $clientPackage->setKnowMore($promotion->getKnowMore());
                $clientPackage->setBenefits([
                    [
                        'additional_information' => sprintf('%d %s', (int) $price->getStartPrice(), $currency),
                        'amount' => [
                            'base' => (int) $price->getStartPrice(),
                            'promotion_bonus' => 0,
                            'total_excluding_tax' => (int) $price->getStartPrice(),
                            'total_including_tax' => (int) $price->getStartPrice(),
                        ],
                        'type' => 'CREDITS',
                        'unit' => $currency,
                        'unit_type' => 'CURRENCY',
                        'schedule' => ['start' => null, 'end' => null],
                    ],
                ]);
                $clientPackage->setTags(['AIRTIME']);
                $clientPackage->setService([
                    'name' => 'Mobile',
                    'subservice' => ['name' => 'AIRTIME'],
                ]);
                $clientPackage->setDestination([
                    'amount' => (int) $price->getStartPrice(),
                    'unit' => $currency,
                    'unit_type' => 'CURRENCY',
                ]);
                $clientPackage->setValidity($promotion->getValidityInfo());
                $this->em->persist($clientPackage);

                // Asociar el paquete a la promoción
                $promotion->addProduct($clientPackage);

                $packagesCreated++;
            }
        }

        $this->em->flush();

        return $packagesCreated;
    }

    /**
     * @throws MyCurrentException
     */
    public function update(CommunicationPromotions $promotion, UpdatePromotionDto $dto): CommunicationPromotions
    {
        if ($dto->getName() !== null) {
            $promotion->setName($dto->getName());
        }
        if ($dto->getDescription() !== null) {
            $promotion->setDescription($dto->getDescription());
        }
        if ($dto->getInfoDescription() !== null) {
            $promotion->setInfoDescription($dto->getInfoDescription());
        }
        if ($dto->getKnowMore() !== null) {
            $promotion->setKnowMore($dto->getKnowMore());
        }
        if ($dto->getTerms() !== null) {
            $promotion->setTerms($dto->getTerms());
        }
        if ($dto->getValidityInfo() !== null) {
            $promotion->setValidityInfo($dto->getValidityInfo());
        }
        if ($dto->getStartAt() !== null) {
            try {
                $promotion->setStartAt(new \DateTimeImmutable($dto->getStartAt()));
            } catch (\Exception) {
                throw new MyCurrentException('INVALID_DATE', 'Invalid date format for startAt', 400);
            }
        }
        if ($dto->getEndAt() !== null) {
            try {
                $promotion->setEndAt(new \DateTimeImmutable($dto->getEndAt()));
            } catch (\Exception) {
                throw new MyCurrentException('INVALID_DATE', 'Invalid date format for endAt', 400);
            }
        }

        $env = $dto->getEnvironment();
        if ($env !== null) {
            if (isset($env['id'])) {
                $environment = $this->em->getRepository(Environment::class)->find($env['id']);
                if ($environment === null) {
                    throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
                }
                $promotion->setEnvironment($environment);
            }
        }

        if ($dto->getProductId() !== null) {
            $product = $this->em->getRepository(CommunicationProduct::class)->find($dto->getProductId());
            if ($product === null) {
                throw new MyCurrentException('PRODUCT_NOT_FOUND', 'Product not found', 404);
            }
            $promotion->setProduct($product);
            if ($promotion->getEnvironment() === null) {
                $promotion->setEnvironment($product->getEnvironment());
            }
        }

        $this->em->flush();

        return $promotion;
    }

    /**
     * @throws MyCurrentException
     */
    public function createFromDto(CreateAdminPromotionDto $dto): CommunicationPromotions
    {
        $product = $this->em->getRepository(CommunicationProduct::class)->find($dto->getProductId());
        if ($product === null) {
            throw new MyCurrentException('PRODUCT_NOT_FOUND', 'Product not found', 404);
        }

        $promotion = new CommunicationPromotions();
        $promotion->setName($dto->getName());
        $promotion->setDescription($dto->getDescription());
        $promotion->setProduct($product);
        $promotion->setEnvironment($product->getEnvironment());
        $promotion->setStartAt(new \DateTimeImmutable($dto->getStartAt()));
        $promotion->setEndAt(new \DateTimeImmutable($dto->getEndAt()));
        $promotion->setTerms($dto->getTerms() ?? []);
        $promotion->setValidityInfo($dto->getValidity() ?? []);

        if ($dto->getInfoDescription() !== null) {
            $promotion->setInfoDescription($dto->getInfoDescription());
        }
        if ($dto->getKnowMore() !== null) {
            $promotion->setKnowMore($dto->getKnowMore());
        }

        foreach ($dto->getProducts() ?? [] as $item) {
            $package = $this->em->getRepository(CommunicationClientPackage::class)->find($item['productId'] ?? 0);
            if ($package !== null) {
                $promotion->addProduct($package);
            }
        }

        $this->em->persist($promotion);
        $this->em->flush();

        return $promotion;
    }
}
