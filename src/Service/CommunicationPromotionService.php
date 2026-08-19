<?php

namespace App\Service;

use App\DTO\CreateAdminPromotionDto;
use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreatePromotionV2Dto;
use App\DTO\UpdatePromotionDto;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Entity\User;
use App\Enums\JobPositionAreaEnum;
use App\Exception\MyCurrentException;
use App\Message\PromotionCreatedMessage;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Service\Pricing\TargetAccountResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CommunicationPromotionService extends CommonService
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
        private readonly MessageBusInterface $messageBus,
        private readonly TargetAccountResolver $targetAccountResolver,
        private readonly CommunicationPackageAdminService $packageAdminService,
        private readonly CommunicationContractService $contractService,
        private readonly CommunicationPromotionEquivalenceService $equivalenceService,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }
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
            $this->logger->warning('createPackagesForPromotion: parámetros inválidos o entidad nula', [
                'promotionId' => $promotion->getId(),
                'amountFrom' => $amountFrom,
                'amountTo' => $amountTo,
                'amountStep' => $amountStep,
                'hasEnvironment' => $environment !== null,
                'hasProduct' => $product !== null,
            ]);
            return 0;
        }

        // 1. Buscar precios existentes en el rango (activos y con vigencia no expirada)
        $now = new \DateTimeImmutable();
        $existingPrices = $this->em->getRepository(CommunicationPrice::class)->createQueryBuilder('p')
            ->where('p.currencyPrice = :currency')
            ->andWhere('p.startPrice >= :from')
            ->andWhere('p.startPrice <= :to')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.validEndAt IS NULL OR p.validEndAt >= :now')
            ->setParameter('currency', $currency)
            ->setParameter('from', $amountFrom)
            ->setParameter('to', $amountTo)
            ->setParameter('active', true)
            ->setParameter('now', $now)
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
            $this->logger->warning('createPackagesForPromotion: no se encontraron precios en el rango', [
                'promotionId' => $promotion->getId(),
                'currency' => $currency,
                'amountFrom' => $amountFrom,
                'amountTo' => $amountTo,
                'amountStep' => $amountStep,
                'referenceRate' => $referenceRate,
            ]);
            return 0;
        }

        // Flush para asignar IDs a los precios nuevos
        $this->em->flush();

        // 2. Obtener las cuentas (tenants) del environment — clientIds vacío
        // = todas las activas; con ids, solo esas (ver TargetAccountResolver,
        // reutilizado también por PackageContractService en modo "rate").
        $accounts = $this->targetAccountResolver->resolve($environment, $clientIds);

        if (empty($accounts)) {
            $this->logger->warning('createPackagesForPromotion: no hay cuentas activas para el environment', [
                'promotionId' => $promotion->getId(),
                'environmentId' => $environment->getId(),
                'clientIdsFilter' => $clientIds,
            ]);
            return 0;
        }

        $packagesCreated = 0;
        $clientsWithNewPackages = [];

        // 3. Por cada precio × cada account → crear (o reutilizar) PricePackage + crear ClientPackage propio de la promoción
        foreach ($filteredPrices as $price) {
            foreach ($accounts as $account) {
                // Buscar PricePackage existente para este tenant+precio+producto
                $pricePackage = $this->em->getRepository(CommunicationPricePackage::class)->findOneBy([
                    'tenant' => $account,
                    'priceUsed' => $price,
                    'product' => $product,
                ]);

                if ($pricePackage === null) {
                    // Crear CommunicationPricePackage nuevo
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
                }
                // Si ya existía: se reutiliza sin duplicar

                // Un paquete pertenece a una sola promoción: solo se omite la creación si
                // ESTA promoción ya tiene un paquete para este tenant y precio (re-ejecución
                // sobre la misma promoción). Promociones distintas crean sus propios paquetes.
                $alreadyInPromotion = $promotion->getProducts()->exists(
                    fn ($key, CommunicationClientPackage $existing) =>
                        $existing->getTenant() === $account
                        && $existing->getPriceClientPackage() === $pricePackage
                );

                if ($alreadyInPromotion) {
                    continue;
                }

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
                $clientPackage->setEnvironment($environment);
                $this->em->persist($clientPackage);

                // Asociar el paquete a la promoción
                $promotion->addProduct($clientPackage);

                $packagesCreated++;

                $client = $account->getClient();
                if ($client !== null) {
                    $clientsWithNewPackages[$client->getId()] = $client;
                }
            }
        }

        $this->em->flush();

        $this->dispatchPromotionEmails($promotion, $clientsWithNewPackages);

        return $packagesCreated;
    }

    private function dispatchPromotionEmails(CommunicationPromotions $promotion, array $clientsWithNewPackages): void
    {
        foreach ($clientsWithNewPackages as $client) {
            $techUsers = $this->em->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->join('u.jobPosition', 'jp')
                ->where('u.company = :client')
                ->andWhere('u.isActive = :active')
                ->andWhere('u.isCheckValidation = :validated')
                ->andWhere('jp.area = :area')
                ->setParameter('client', $client)
                ->setParameter('active', true)
                ->setParameter('validated', true)
                ->setParameter('area', JobPositionAreaEnum::TECHNOLOGY)
                ->getQuery()
                ->getResult();

            foreach ($techUsers as $user) {
                $this->messageBus->dispatch(new PromotionCreatedMessage(
                    $promotion->getId(),
                    $user->getEmail(),
                    $user->getFirstName(),
                    $client->getId(),
                    $client->getContractWith(),
                ));
            }
        }
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

        if ($dto->getEnvironmentId() !== null) {
            $environment = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
            if ($environment === null) {
                throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
            }
            $promotion->setEnvironment($environment);
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

        if ($dto->getPriority() !== null) {
            $promotion->setPriority($dto->getPriority());
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

    /**
     * Alta de promoción V2 (Fase 5B, catálogo compartido) — genera un
     * CommunicationPackage por cada monto de [amountFrom, amountTo] (paso
     * amountStep), marcado con esta promoción y vigente SOLO durante
     * [startAt, endAt]. A diferencia de createPackagesForPromotion()
     * (legacy), no depende de un producto de origen ni genera nada por
     * cliente — la visibilidad la decide PackageCatalogResolver como a
     * cualquier paquete V2.
     *
     * NO crea contrato: el precio es responsabilidad exclusiva del flujo
     * de CommunicationContract (aparte, ver CommunicationContractService)
     * — createV2() solo vincula los contratos propios que un tenant YA
     * tenga sobre el paquete regular equivalente (linkTenantContracts
     * ToPromotionPackages()), para que la promoción no quede oculta para
     * quien ya compraba ese paquete (ver PackageCatalogResolver::
     * catalogFor()). Un paquete sin ningún contrato propio vinculado
     * queda visible igual, al precio derivado del catálogo (MAX de
     * producto + margen), hasta que se le cree un contrato a mano.
     *
     * Las equivalencias por proveedor (quién despacha cada tramo) se
     * auto-pueblan al final vía CommunicationPromotionEquivalenceService
     * (Fase 5D) — cada proveedor con capacidad de auto-poblado
     * (ProviderPromotionCatalogInterface) cubre los tramos que pueda; los
     * huecos quedan reportados en el resultado para curar a mano. Sin
     * equivalencia (ni automática ni manual), ese proveedor no despacha el
     * tramo (ProviderDispatchResolver en modo estricto para paquetes de
     * promoción).
     *
     * @throws MyCurrentException
     */
    public function createV2(CreatePromotionV2Dto $dto): CreatePromotionV2Result
    {
        $environment = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        try {
            $startAt = new \DateTimeImmutable((string) $dto->getStartAt());
            $endAt = new \DateTimeImmutable((string) $dto->getEndAt());
        } catch (\Exception) {
            throw new MyCurrentException('INVALID_DATE', 'Invalid date format', 400);
        }

        $promotion = new CommunicationPromotions();
        $promotion->setName((string) $dto->getName());
        $promotion->setDescription((string) $dto->getDescription());
        $promotion->setEnvironment($environment);
        $promotion->setStartAt($startAt);
        $promotion->setEndAt($endAt);
        if ($dto->getKnowMore() !== null) {
            $promotion->setKnowMore($dto->getKnowMore());
        }
        if ($dto->getInfoDescription() !== null) {
            $promotion->setInfoDescription($dto->getInfoDescription());
        }

        $this->em->persist($promotion);
        $this->em->flush();

        $batchDto = new CreateCommunicationPackageBatchDto(
            nameTemplate: (string) $dto->getPackageNameTemplate(),
            descriptionTemplate: (string) $dto->getPackageDescriptionTemplate(),
            knowMore: $dto->getKnowMore(),
            fromAmount: $dto->getAmountFrom(),
            toAmount: $dto->getAmountTo(),
            step: $dto->getAmountStep(),
            destinationCurrency: $dto->getDestinationCurrency(),
            isActive: true,
            activeStartAt: $dto->getStartAt(),
            activeEndAt: $dto->getEndAt(),
            displayOrder: $dto->getDisplayOrder(),
            benefits: $dto->getBenefits(),
            tags: $dto->getTags(),
            service: $dto->getService(),
            validity: $dto->getValidity(),
        );

        $packages = $this->packageAdminService->createBatch($batchDto);

        foreach ($packages as $package) {
            $package->setPromotion($promotion);
        }
        $this->em->flush();

        $tenantContractsLinked = $this->contractService->linkTenantContractsToPromotionPackages($packages);

        $equivalences = $this->equivalenceService->populateEquivalences($promotion, $packages);

        return new CreatePromotionV2Result($promotion, $packages, $tenantContractsLinked, $equivalences);
    }
}
