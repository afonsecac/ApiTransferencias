<?php

namespace App\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\DTO\NotificationDraft;
use App\DTO\ReserveRecharge;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSaleHistory;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Message\SalePackageMessage;
use App\Message\SaleRechargeMessage;
use App\Provider\Contract\PackageCustomer;
use App\Provider\Contract\PackageSaleProviderInterface;
use App\Provider\Contract\PackageSalePoint;
use App\Provider\Contract\PackageSaleRequest;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\RechargeRequest;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\BalanceOperationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;

class CommunicationSaleService extends CommonService
{

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Symfony\Bundle\SecurityBundle\Security $security
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameters
     * @param \Symfony\Component\Mailer\MailerInterface $mailer
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
     * @param \App\Repository\EnvironmentRepository $environmentRepository
     * @param \App\Repository\SysConfigRepository $sysConfigRepo
     * @param \Symfony\Component\Serializer\SerializerInterface $serializer
     * @param \App\Service\ConfigureSequenceService $configureSequence
     * @param \Symfony\Component\Messenger\MessageBusInterface $messageBus
     * @param \App\Service\HistoricalSaleService $historicalSaleService
     * @param \App\Service\BalanceService $balanceService
     */
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
        private readonly ProviderRegistry $providerRegistry,
        private readonly ProviderResolver $providerResolver,
        private readonly ProviderContextFactory $providerContextFactory,
        private readonly ConfigureSequenceService $configureSequence,
        private readonly MessageBusInterface $messageBus,
        private readonly HistoricalSaleService $historicalSaleService,
        private readonly BalanceService $balanceService,
        private readonly NotificationCenterService $notificationCenter,
    ) {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer
        );
    }

    private const DISPATCH_ENABLED_KEY = 'communications.dispatch.enabled';

    private function isDispatchEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(self::DISPATCH_ENABLED_KEY) !== '0';
    }

    /**
     * Activa un lote de ventas RESERVED cuya promoción ya comenzó.
     * Persiste todos los cambios antes de despachar mensajes para evitar race conditions.
     *
     * @param CommunicationSaleRecharge[] $sales
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function activateReservedSales(array $sales): int
    {
        $now = new \DateTimeImmutable('now');
        $activated = 0;
        $toDispatch = [];

        foreach ($sales as $sale) {
            $promotion = $sale->getPromotion();
            if ($promotion instanceof CommunicationPromotions && $promotion->getEndAt() < $now) {
                $sale->setState(CommunicationStateEnum::REJECTED);
                $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                $sale->setTransactionStatus(['result' => ['message' => 'Promotion expired before activation']]);
                $this->logger->info("Reserved sale {$sale->getId()} rejected: promotion expired.");
                continue;
            }

            $sale->setState(CommunicationStateEnum::PENDING);

            $history = new CommunicationSaleHistory();
            $history->setState(CommunicationStateEnum::PENDING);
            $history->setSale($sale);
            $this->em->persist($history);

            $toDispatch[] = $sale;
            $activated++;
            $this->logger->info("Reserved sale {$sale->getId()} activated.");
        }

        $this->em->flush();

        if ($this->isDispatchEnabled()) {
            foreach ($toDispatch as $sale) {
                $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
            }
        } else {
            $this->logger->info('Communications dispatch disabled: ' . count($toDispatch) . ' activated sale(s) queued in DB, pending dispatch.');
        }

        return $activated;
    }

    /**
     * @param \App\DTO\ReserveRecharge $reserveDto
     * @return \App\Entity\CommunicationSaleRecharge|null
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function processReserve(ReserveRecharge $reserveDto): CommunicationSaleRecharge|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            throw new AccessDeniedException();
        }
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $package = $clientPackageRepo->getPackageByIdForReserve(
            $reserveDto->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        /** @var \App\Repository\CommunicationPromotionsRepository $promotionRepo */
        $promotionRepo = $this->em->getRepository(CommunicationPromotions::class);
        $promotion = $promotionRepo->getFuturePromotionById(
            $reserveDto->getPromotionId(),
            $reserveDto->getPackageId()
        );
        if (is_null($promotion)) {
            throw new MyCurrentException('COM007', 'The promotion is not active to reserves');
        }

        $this->assertRechargeableProduct($package);

        // El proveedor es propiedad del producto, no de la cuenta — se
        // valida y se congela ANTES de construir la venta (ver
        // resolveAndGuardProvider()).
        $productProvider = $this->resolveAndGuardProvider($user, $package);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setTenant($user);
        $recharge->setState(CommunicationStateEnum::RESERVED);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
        $recharge->setProvider($productProvider);
        $recharge->setPromotionId($reserveDto->getPromotionId());
        $recharge->setPackageId($reserveDto->getPackageId());
        $recharge->setPhoneNumber($reserveDto->getPhoneNumber());
        $recharge->setClientTransactionId($reserveDto->getClientTransactionId());
        $recharge->setPackage($package);
        $recharge->setPromotion($promotion);
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        $recharge->setTransactionId($transactionId);
        $recharge->setAmount($package->getAmount());
        $recharge->setCurrency($package->getCurrency());
        $recharge->getCalculatePrice();

        $this->em->beginTransaction();
        try {
            // Lock pesimista por cuenta + saldo-menos-reservado: cierra la
            // condición de carrera entre ventas concurrentes de la misma
            // cuenta. Ver docs/balance-check-architecture.md (Fase 1).
            if (!$this->balanceService->hasAvailableBalance($user, $package->getAmount())) {
                throw new MyCurrentException('COM001', 'Insufficient balance');
            }
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::RESERVED);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            if ($e instanceof MyCurrentException) {
                throw $e;
            }
            if (str_contains($e->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException('COM005', 'Duplicate transaction by customer');
            }
            if (str_contains($e->getMessage(), "unique_transaction_id")) {
                throw new MyCurrentException('COM005', 'Duplicate transaction');
            }
            throw $e;
        }

        return $recharge;
    }

    /**
     * @param \App\Entity\CommunicationSaleRecharge $recharge
     * @return \App\Entity\CommunicationSaleRecharge|null
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function processRecharge(CommunicationSaleRecharge $recharge): CommunicationSaleRecharge|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            throw new AccessDeniedException();
        }
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $package = $clientPackageRepo->getPackageById(
            $recharge->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }

        $this->assertRechargeableProduct($package);

        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        // El proveedor es propiedad del producto, no de la cuenta — se
        // valida y se congela ANTES de construir la venta (ver
        // resolveAndGuardProvider()).
        $productProvider = $this->resolveAndGuardProvider($user, $package);

        $recharge->setTransactionId($transactionId);
        $recharge->setPackage($package);
        $recharge->setTenant($user);
        $recharge->setAmount($package->getAmount());
        $recharge->setCurrency($package->getCurrency());
        $recharge->getCalculatePrice();
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
        $recharge->setProvider($productProvider);

        $this->em->beginTransaction();
        try {
            // Lock pesimista por cuenta + saldo-menos-reservado: cierra la
            // condición de carrera entre ventas concurrentes de la misma
            // cuenta. Ver docs/balance-check-architecture.md (Fase 1).
            if (!$this->balanceService->hasAvailableBalance($user, $package->getAmount())) {
                throw new MyCurrentException('COM001', 'Insufficient balance');
            }
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $ex) {
            $this->em->rollback();
            if ($ex instanceof MyCurrentException) {
                throw $ex;
            }
            if (str_contains($ex->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException("103", 'Se envió más de una Recarga en la misma transacción.');
            }
            if (str_contains($ex->getMessage(), "unique_transaction_id")) {
                throw new MyCurrentException("103", 'Se envió más de una Recarga en la misma transacción.');
            }
            $this->logger->error("Recharge persist failed: " . $ex->getMessage());
            throw $ex;
        }

        if ($this->isDispatchEnabled()) {
            $this->messageBus->dispatch(new SaleRechargeMessage($recharge->getId()));
        } else {
            $this->logger->info("Communications dispatch disabled: recharge {$recharge->getId()} saved, pending dispatch.");
        }

        return $recharge;
    }

    /**
     * @param int $saleId
     * @return void
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function tryAgainWithTransaction(int $saleId): void
    {
        $recharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
        if ($recharge === null) {
            return;
        }
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
        $this->em->flush();
        $this->messageBus->dispatch(new SaleRechargeMessage($saleId));
    }

    /**
     * @param int $saleId
     * @return void
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function invokeRechargeCommunication(int $saleId): void
    {
        $orderId = null;
        $body = [];
        $bodyCheck = [];
        $saleRecharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
        if (is_null($saleRecharge)) {
            return;
        }
        {
            // No procesar ventas reservadas — deben ser activadas por app:activate-reserved-sales
            if ($saleRecharge->getState() === CommunicationStateEnum::RESERVED) {
                $this->logger->info("Skipping recharge {$saleId}: still RESERVED, waiting for promotion to start.");
                return;
            }
            $user = $saleRecharge->getTenant();
            if (!$user instanceof Account) {
                $saleRecharge->setState(CommunicationStateEnum::PENDING);
                $rechargeInfo = [
                    'result' => [
                        'message' => 'Unexpected user',
                    ],
                ];
                $saleRecharge->setTransactionStatus($rechargeInfo);
                $this->em->flush();

                return;
            }
            if (!$this->claimForSending($saleRecharge)) {
                $this->logger->info("Skipping recharge {$saleId}: already being processed (stateProcess={$saleRecharge->getStateProcess()})");
                return;
            }
            try {
                $balance = $this->balanceService->balance($user->getId());
                /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
                $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
                $package = $clientPackageRepo->getPackageById(
                    $saleRecharge->getPackageId(),
                    $user
                );
                if ($balance->amount < $package?->getAmount()) {
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $saleRecharge->setStateProcess(CommunicationStateEnum::REJECTED->value);
                    $rechargeInfo = [
                        'result' => [
                            'message' => 'The balance aren`t sufficient',
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($rechargeInfo);
                    $this->em->flush();

                    return;

                }
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
                $this->em->flush();
                $saleRecharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
                $environment = $this->em->getRepository(Environment::class)->find($user->getEnvironment()?->getId());
                if (is_null($environment)) {
                    $saleRecharge->setState(CommunicationStateEnum::FAILED);
                    $saleRecharge->setStateProcess(CommunicationStateEnum::FAILED->value);
                    $rechargeInfo = [
                        'result' => [
                            'message' => 'Unexpected environment',
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($rechargeInfo);
                    $this->em->flush();

                    return;
                }
                $productCode = $package?->getPriceClientPackage()?->getProduct()?->getPackageId();
                if (!is_null($saleRecharge->getPromotionId())) {
                    /** @var \App\Repository\CommunicationPromotionsRepository $promotionRepo */
                    $promotionRepo = $this->em->getRepository(CommunicationPromotions::class);
                    $promotion = $promotionRepo->getActivePromotionById(
                        $saleRecharge->getPromotionId()
                    );
                    if (!is_null($promotion)) {
                        $productCode = $promotion->getProduct()?->getPackageId();
                    }
                    $saleRecharge->setPromotionId($saleRecharge->getPromotionId());
                    $saleRecharge->setPromotion($promotion);
                } elseif ($package?->getPromotionItems()->count() === 1) {
                    $promotion = $package->getPromotionItems()->first();
                    $saleRecharge->setPromotionId($promotion->getId());
                    $saleRecharge->setPromotion($promotion);
                    $productCode = $promotion?->getProduct()?->getPackageId();
                }

                $destination = (object)$package?->getDestination();

                // El proveedor se resuelve ANTES de aplicar cualquier
                // sustitución de sandbox: el bloque de abajo es una
                // convención de prueba propia de ETECSA (código de producto
                // fijo "100" + número de teléfono de prueba), no una regla
                // general de "entorno TEST" — aplicarla a otro proveedor
                // (p.ej. DTOne) le envía un product_id inventado y DTOne lo
                // rechaza como "Product is not available in your account"
                // (confirmado en vivo el 2026-08-03: nunca era un problema de
                // permisos de la cuenta de DTOne).
                $provider = $this->providerResolver->resolveForSale($saleRecharge);

                $phoneLength = strlen($saleRecharge->getPhoneNumber());
                $checkPhone = substr($saleRecharge->getPhoneNumber(), $phoneLength - 2, $phoneLength);
                $phoneNumber = $saleRecharge->getPhoneNumber();
                if ($environment->getType() === 'TEST' && $provider === CommunicationProviderEnum::ETECSA) {
                    $phoneNumber = $checkPhone === "60" ? $this->parameters->get(
                        'app.phoneNumber'
                    ) : $saleRecharge->getPhoneNumber();
                    $productCode = "100";
                }

                $body = [
                    'phoneNumber' => $phoneNumber,
                    'productCode' => $productCode,
                    'productPrice' => round($destination->amount, 2),
                    'transactionId' => $saleRecharge->getTransactionId(),
                    'environment' => $environment->getType(),
                ];

                $adapter = $this->providerRegistry->getFor($provider, RechargeProviderInterface::class);
                $context = $this->providerContextFactory->forSale($saleRecharge);
                $request = new RechargeRequest(
                    transactionId: $saleRecharge->getTransactionId(),
                    phoneNumber: $phoneNumber,
                    productExternalId: (string) $productCode,
                    destinationAmount: (float) $destination->amount,
                    destinationUnit: $saleRecharge->getCurrency() ?? 'CUP',
                );

                $dispatchResult = $adapter->recharge($context, $request);
                $saleRecharge->setTransactionStatus($dispatchResult->raw);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);

                if ($dispatchResult->outcome === ProviderOutcomeEnum::REJECTED) {
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $saleRecharge->getId(),
                        CommunicationStateEnum::REJECTED
                    );

                    $comInfo = [
                        'error' => [
                            'message' => sprintf(
                                "action=Recharge, Message=%s",
                                $dispatchResult->message ?? 'Unexpected message during the sale'
                            ),
                            'orderID' => $orderId,
                            'code' => sprintf(
                                "COM%s",
                                $dispatchResult->providerCode
                            ),
                            'transactionID' => $saleRecharge->getTransactionId(),
                            'body' => $body,
                            'bodyCheck' => $bodyCheck,
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($comInfo);
                }
                $this->em->flush();
                // Solo despachar check si el envío fue aceptado (ACCEPTED). Si el
                // resultado es UNKNOWN (timeout/error de transporte: no sabemos si
                // la petición llegó al proveedor) no se reprograma nada aquí — el
                // cron de pendientes (CheckStatusTask) la recogerá más tarde.
                // Jamás debe reintentarse el ENVÍO mismo tras un UNKNOWN: eso
                // podría cobrar dos veces la misma recarga.
                if ($dispatchResult->outcome === ProviderOutcomeEnum::ACCEPTED) {
                    $this->messageBus->dispatch(new CheckSaleMessage($saleId), [new DelayStamp(2000)]);
                }
            } catch (\Exception $ex) {
                // Los errores de transporte del proveedor ya vienen absorbidos como
                // ProviderOutcomeEnum::UNKNOWN por el adaptador (ver
                // EtecsaCommunicationProvider::recharge()) y no llegan aquí. Este
                // catch cubre errores inesperados fuera de la llamada al proveedor
                // (persistencia, etc.), igual que el catch genérico que ya existía.
                $saleRecharge->setState(CommunicationStateEnum::PENDING);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
                $comInfo = [
                    'error' => [
                        'message' => sprintf(
                            "action=Recharge, Message=%s",
                            'Unexpected error'
                        ),
                        'code' => 'COM000',
                        'transactionID' => $saleRecharge->getTransactionId(),
                    ],
                ];
                $saleRecharge->setTransactionStatus($comInfo);
                $this->historicalSaleService->createHistoricalCommunication(
                    $saleId,
                    CommunicationStateEnum::PENDING
                );
                if ($ex instanceof Exception\UniqueConstraintViolationException) {
                    if (strpos($ex->getPrevious()?->getMessage() ?? '', "unique_identification_client") !== false) {
                        $comInfo = [
                            'error' => [
                                'code' => 'COM005',
                                'message' => sprintf(
                                    "action=Recharge, Message=%s",
                                    'Duplicate transaction by customer'
                                ),
                                'transactionID' => $saleRecharge->getTransactionId(),
                            ],
                        ];
                        $saleRecharge->setTransactionStatus($comInfo);
                        $this->historicalSaleService->createHistoricalCommunication(
                            $saleId,
                            CommunicationStateEnum::REJECTED,
                            $comInfo
                        );
                    }
                }
            }

            $this->em->flush();
        }
    }

    /**
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function executeSale(CommunicationSalePackage $sale): CommunicationSalePackage|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            throw new AccessDeniedException();
        }
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $package = $clientPackageRepo->getPackageById(
            $sale->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSalePackage::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'02'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        // El proveedor es propiedad del producto, no de la cuenta — se
        // valida y se congela ANTES de construir la venta (ver
        // resolveAndGuardProvider()).
        $productProvider = $this->resolveAndGuardProvider($user, $package);

        $sale->setTransactionId($transactionId);
        $sale->setPackage($package);
        $sale->setTenant($user);
        $sale->setAmount($package->getPriceClientPackage()?->getAmount());
        $sale->setCurrency($package->getPriceClientPackage()?->getCurrency());
        $sale->getCalculatePrice();
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);
        $sale->setProvider($productProvider);

        $commercialOffice = $this->em->getRepository(CommunicationOffice::class)->findOneBy([
            'id' => $sale->commercialOfficeId,
            'environment' => $user->getEnvironment(),
        ]);
        if (is_null($commercialOffice)) {
            throw new MyCurrentException('COM006', 'The commercial office don\'t exist');
        }
        $nationality = $this->em->getRepository(CommunicationNationality::class)->findOneBy([
            'id' => $sale->nationalityId,
            'environment' => $user->getEnvironment(),
        ]);
        if (is_null($nationality)) {
            throw new MyCurrentException('COM007', 'The nationality don\'t exist');
        }
        $sale->setCommercialOffice($commercialOffice);
        $sale->setNationality($nationality);

        $this->em->beginTransaction();
        try {
            // Lock pesimista por cuenta + saldo-menos-reservado: cierra la
            // condición de carrera entre ventas concurrentes de la misma
            // cuenta. Ver docs/balance-check-architecture.md (Fase 1).
            if (!$this->balanceService->hasAvailableBalance($user, $package->getPriceClientPackage()?->getAmount())) {
                throw new MyCurrentException('COM001', 'Insufficient balance');
            }
            $this->em->persist($sale);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($sale);
            $this->em->persist($comHistoric);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $ex) {
            $this->em->rollback();
            if ($ex instanceof MyCurrentException) {
                throw $ex;
            }
            if (str_contains($ex->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException("102", 'Se envió más de una Activación en la misma transacción.');
            }
            throw $ex;
        }

        if ($this->isDispatchEnabled()) {
            $this->messageBus->dispatch(new SalePackageMessage($sale->getId()));
        } else {
            $this->logger->info("Communications dispatch disabled: sale package {$sale->getId()} saved, pending dispatch.");
        }

        return $sale;
    }

    /**
     * @param $saleId
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function executeNewSaleInfo($saleId): void
    {
        $sale = $this->em->getRepository(CommunicationSalePackage::class)->findOneBy([
            'id' => $saleId
        ]);
        if (is_null($sale) || $sale->getState() !== CommunicationStateEnum::PENDING) {
            return;
        }
        if (!$this->claimForSending($sale)) {
            $this->logger->info("Skipping sale {$saleId}: already being processed (stateProcess={$sale->getStateProcess()})");
            return;
        }

        $transactionId = $sale->getTransactionId();
        try {
            $user = $sale->getTenant();
            if (!$user instanceof Account) {
                $this->failSale($sale, 'Unexpected user');
                return;
            }
            $commercialOffice = $sale->getCommercialOffice();
            if (is_null($commercialOffice)) {
                $this->failSale($sale, 'Missing commercial office');
                return;
            }
            $nationality = $sale->getNationality();
            if (is_null($nationality)) {
                $this->failSale($sale, 'Missing nationality');
                return;
            }
            $package = $sale->getPackage();
            if (!$package instanceof CommunicationClientPackage) {
                $this->failSale($sale, 'Missing package');
                return;
            }
            $officeComId = $commercialOffice->getComId();
            $packageProductId = $package->getPriceClientPackage()?->getProduct()?->getPackageId();

            $provider = $this->providerResolver->resolveForSale($sale);
            $adapter = $this->providerRegistry->getFor($provider, PackageSaleProviderInterface::class);
            $context = $this->providerContextFactory->forSale($sale);
            $request = new PackageSaleRequest(
                transactionId: $transactionId,
                productExternalId: $packageProductId !== null ? (string) $packageProductId : '',
                productKind: $package->getPriceClientPackage()?->getProduct()?->getPackageType(),
                phoneNumber: null,
                customer: new PackageCustomer(
                    identificationNumber: $sale->getIdentificationNumber(),
                    name: $sale->getName(),
                    identificationType: $sale->getIdentificationType() ?? 1,
                    arrivalDate: $sale->getArrivalAt(),
                    nationalityExternalId: $nationality->getComId(),
                ),
                salePoint: new PackageSalePoint(
                    commercialOfficeExternalId: $officeComId !== null ? (int) $officeComId : null,
                    provinceExternalId: $commercialOffice->getProvince()?->getComId(),
                    isAirport: $commercialOffice->isIsAirport(),
                ),
            );

            $dispatchResult = $adapter->sellPackage($context, $request);

            if ($dispatchResult->outcome === ProviderOutcomeEnum::ACCEPTED) {
                $sale->setState(CommunicationStateEnum::PENDING);
            } elseif ($dispatchResult->outcome === ProviderOutcomeEnum::FAILED) {
                $sale->setState(CommunicationStateEnum::FAILED);
            }
            $sale->setTransactionStatus($dispatchResult->raw);
            $sale->setStateProcess(CommunicationStateEnum::PENDING->value);

            if ($dispatchResult->outcome === ProviderOutcomeEnum::UNKNOWN) {
                // Transporte falló: no sabemos si el proveedor recibió la venta.
                // No se reprograma el check inmediato — el cron de pendientes la
                // recogerá más tarde, igual que ante cualquier excepción antes.
                $this->em->flush();
                $this->logger->error("Sale {$saleId} execution error: " . ($dispatchResult->message ?? 'unknown transport error'));

                return;
            }

            $this->em->flush();
            $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()), [new DelayStamp(2000)]);
        } catch (\Exception $exc) {
            $sale->setStateProcess(CommunicationStateEnum::PENDING->value);
            $this->em->flush();
            $this->logger->error("Sale {$saleId} execution error: " . $exc->getMessage());
        }
    }

    /**
     * Marca atómicamente una venta como 'SENDING' usando un UPDATE condicional en BD.
     * Devuelve true solo si esta instancia del worker ganó la carrera; false si otra
     * ya tomó la venta (0 filas afectadas). Esto previene envíos duplicados al proveedor
     * externo cuando múltiples workers reciben el mismo mensaje.
     */
    /**
     * Atomic transition PENDING→COMPLETED. Returns true only if this worker won the race.
     * Prevents duplicate balance_operation inserts when multiple workers check the same sale.
     */
    private function claimForCompleting(CommunicationSaleInfo $sale): bool
    {
        $table = $this->em->getClassMetadata(CommunicationSaleInfo::class)->getTableName();

        $affected = $this->em->getConnection()->executeStatement(
            "UPDATE {$table} SET state = :completed, state_process = :completedProcess WHERE id = :id AND state = :pending",
            [
                'completed'        => CommunicationStateEnum::COMPLETED->value,
                'completedProcess' => CommunicationStateEnum::COMPLETED->value,
                'id'               => $sale->getId(),
                'pending'          => CommunicationStateEnum::PENDING->value,
            ]
        );

        if ($affected > 0) {
            $this->em->refresh($sale);
        }

        return $affected > 0;
    }

    /**
     * El proveedor es propiedad del producto, no de la cuenta: se lee de
     * package.priceClientPackage.product.provider y se valida contra
     * ProviderResolver::allowedForClient() ANTES de admitir la venta. Esto
     * es lo que impide que el routing de un cliente (Fase 2) mande un
     * productCode de un proveedor a otro distinto — el error se detecta en
     * admisión, no como un fallo silencioso en el despacho.
     */
    private function resolveAndGuardProvider(Account $user, CommunicationClientPackage $package): string
    {
        $productProvider = $package->getPriceClientPackage()?->getProduct()?->getProvider()
            ?? CommunicationProviderEnum::ETECSA->value;

        $client = $user->getClient();
        if ($client !== null) {
            $allowed = array_map(
                static fn (CommunicationProviderEnum $p) => $p->value,
                $this->providerResolver->allowedForClient($client, $user->getEnvironment()?->getId()),
            );

            if (!in_array($productProvider, $allowed, true)) {
                throw new MyCurrentException(
                    'PROVIDER_NOT_ALLOWED_FOR_CLIENT',
                    'El paquete pertenece a un proveedor no habilitado para este cliente',
                    Response::HTTP_CONFLICT,
                );
            }
        }

        return $productProvider;
    }

    /**
     * Un producto PIN_PURCHASE (código/voucher que el cliente debe canjear)
     * nunca se vende como recarga: se acredita al teléfono, no entrega un
     * código. Decisión de negocio: todo lo que sea PIN_PURCHASE se considera
     * un paquete, vendible solo por executeSale(), nunca por
     * processReserve()/processRecharge().
     */
    private function assertRechargeableProduct(CommunicationClientPackage $package): void
    {
        $packageType = $package->getPriceClientPackage()?->getProduct()?->getPackageType();

        if ($packageType !== null && str_contains($packageType, 'PIN_PURCHASE')) {
            throw new MyCurrentException(
                'PRODUCT_REQUIRES_PACKAGE_SALE',
                'Este producto se canjea por código y solo puede venderse como paquete, no como recarga',
                Response::HTTP_CONFLICT,
            );
        }
    }

    private function claimForSending(CommunicationSaleInfo $sale): bool
    {
        $table = $this->em->getClassMetadata(CommunicationSaleInfo::class)->getTableName();

        $affected = $this->em->getConnection()->executeStatement(
            "UPDATE {$table} SET state_process = :sending WHERE id = :id AND state_process = :created",
            [
                'sending' => 'SENDING',
                'id'      => $sale->getId(),
                'created' => CommunicationStateEnum::CREATED->value,
            ]
        );

        if ($affected > 0) {
            $this->em->refresh($sale);
        }

        return $affected > 0;
    }

    private function failSale(CommunicationSaleInfo $sale, string $reason): void
    {
        $sale->setState(CommunicationStateEnum::FAILED);
        $sale->setStateProcess(CommunicationStateEnum::FAILED->value);
        $sale->setTransactionStatus(['result' => ['message' => $reason]]);
        $this->em->flush();
        $this->logger->error("Sale {$sale->getId()} failed: {$reason}");

        // Punto único por el que pasa toda transición a FAILED: aquí se
        // engancha la notificación in-app, independiente del email.
        $client = $sale->getTenant()?->getClient();
        if ($client !== null) {
            $isRecharge = $sale instanceof CommunicationSaleRecharge;
            $this->notificationCenter->notifyClient($client, new NotificationDraft(
                type: $isRecharge ? NotificationTypeEnum::RECHARGE_FAILED : NotificationTypeEnum::SALE_FAILED,
                title: sprintf('%s fallida (#%d)', $isRecharge ? 'Recarga' : 'Venta', $sale->getId()),
                level: NotificationLevelEnum::ERROR,
                body: $reason,
                link: '/apps/sales/' . $sale->getId(),
                data: ['saleId' => $sale->getId(), 'transactionId' => $sale->getTransactionId(), 'reason' => $reason],
                environmentId: $sale->getTenant()?->getEnvironment()?->getId(),
            ));
        }
    }

    /**
     * @throws MyCurrentException
     */
    public function checkSaleInfo(int $saleId): CommunicationSaleInfo
    {
        $communicationSale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        if (is_null($communicationSale)) {
            throw new MyCurrentException('SALE_NOT_FOUND', 'Sale not found', Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $tenant = $communicationSale->getTenant();

        $authorized = false;
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $authorized = true;
        } elseif ($user instanceof User && $user->getCompany() !== null) {
            $authorized = $tenant?->getClient()?->getId() === $user->getCompany()->getId();
        } elseif ($user instanceof Account) {
            $authorized = $user->getId() === $tenant?->getId();
        }

        if (!$authorized) {
            throw new MyCurrentException('FORBIDDEN_SALE_CHECK', 'Not authorized to check this sale', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->checkStatusOrder($saleId);
            $communicationSale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        } catch (\Exception $exc) {
            $this->logger->info($exc->getMessage());
        }

        return $communicationSale;
    }

    /**
     * @param int $saleId
     * @return void
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function checkStatusSaleInfo(int $saleId): void
    {
        $this->checkStatusOrder($saleId);
    }

    public function checkStatusOrder(int $saleId): void
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        if (is_null($sale) || $sale->getState() !== CommunicationStateEnum::PENDING) {
            return;
        }
        // No hacer check si la transacción aún no fue enviada a ETECSA
        $stateProcess = $sale->getStateProcess();
        if ($stateProcess === null
            || $stateProcess === CommunicationStateEnum::CREATED->value
            || $stateProcess === 'SENDING'
        ) {
            $this->logger->info("Check skipped for sale {$saleId}: not yet sent to provider (stateProcess={$stateProcess})");
            return;
        }
        $tenant = $sale->getTenant();
        if (is_null($tenant)) {
            return;
        }

        $isRecharge = $sale instanceof CommunicationSaleRecharge;

        try {
            $provider = $this->providerResolver->resolveForSale($sale);
            $context = $this->providerContextFactory->forSale($sale);
            $query = new ProviderStatusQuery(transactionId: $sale->getTransactionId());

            if ($isRecharge) {
                $adapter = $this->providerRegistry->getFor($provider, RechargeProviderInterface::class);
                $statusResult = $adapter->fetchRechargeStatus($context, $query);
            } else {
                $adapter = $this->providerRegistry->getFor($provider, PackageSaleProviderInterface::class);
                $statusResult = $adapter->fetchPackageSaleStatus($context, $query);
            }

            if ($statusResult->abortWithoutPersisting) {
                // El proveedor reporta la venta completada pero este tipo de venta
                // no puede confirmarse desde esta respuesta: no se toca la entidad.
                return;
            }

            $sale->setTransactionStatus($statusResult->raw);

            if ($statusResult->outcome === ProviderOutcomeEnum::COMPLETED) {
                if ($statusResult->providerReference !== null) {
                    $sale->setTransactionOrder($statusResult->providerReference);
                }

                // Atomic claim: only one concurrent worker proceeds to create the balance.
                if (!$this->claimForCompleting($sale)) {
                    $this->logger->info("Sale {$saleId}: already completed by another worker, skipping balance.");
                    return;
                }

                try {
                    $this->balanceService->createSaleBalance($tenant, $sale);
                } catch (\Exception $balanceEx) {
                    $this->logger->critical("BALANCE FAILED for sale {$sale->getId()}: " . $balanceEx->getMessage());
                }
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::COMPLETED,
                    $statusResult->raw
                );
            } elseif ($statusResult->outcome === ProviderOutcomeEnum::REJECTED) {
                $sale->setState(CommunicationStateEnum::REJECTED);
                $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                if ($statusResult->recordHistory) {
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::REJECTED,
                        $statusResult->raw
                    );
                }
            } elseif ($statusResult->recordHistory) {
                // ACCEPTED/PENDING/RETRYABLE/UNKNOWN se mapean todos aquí: la venta
                // sigue PENDING (ya lo estaba), solo se registra el histórico.
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    $statusResult->recordHistoryWithoutData ? [] : $statusResult->raw
                );
            }
            $this->em->flush();
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->logger->error($message);
            if ($e->getCode() === 404) {
                $currentStatus = $sale->getTransactionStatus();
                $retryCount = (int) ($currentStatus['retryCount'] ?? 0);

                if ($sale instanceof CommunicationSaleRecharge && $retryCount < 3) {
                    $now = new \DateTimeImmutable();
                    $lastRetryAt = isset($currentStatus['lastRetryAt'])
                        ? new \DateTimeImmutable($currentStatus['lastRetryAt'])
                        : null;
                    $referenceTime = $lastRetryAt ?? $sale->getCreatedAt();
                    $secondsElapsed = $now->getTimestamp() - $referenceTime->getTimestamp();

                    if ($secondsElapsed >= 4 * 3600) {
                        $currentStatus['retryCount'] = $retryCount + 1;
                        $currentStatus['lastRetryAt'] = $now->format(\DateTimeInterface::ATOM);
                        $sale->setTransactionStatus($currentStatus);
                        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);
                        $this->em->flush();
                        $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
                        $this->logger->info("Sale {$saleId}: not found in ApiComm, resending (attempt {$currentStatus['retryCount']})");
                    }
                } else {
                    $now = new \DateTimeImmutable();
                    $currentStatus['gatewayMissing'] = true;
                    $currentStatus['markedRejectedAt'] = $now->format(\DateTimeInterface::ATOM);
                    $currentStatus['reason'] = $sale instanceof CommunicationSaleRecharge
                        ? 'Not found in ApiComm after max retries'
                        : 'Not found in ApiComm';
                    $sale->setState(CommunicationStateEnum::REJECTED);
                    $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                    $sale->setTransactionStatus($currentStatus);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::REJECTED,
                        $currentStatus
                    );
                    $this->em->flush();
                    $this->logger->critical("Sale {$saleId}: not found in ApiComm, marked REJECTED for manual review.");
                }
            } elseif ($e->getCode() === 400) {
                $comInfo = [
                    'status' => [
                        'message' => sprintf(
                            "action=Recharge, Message=%s",
                            'La orden aun esta en procesamiento'
                        ),
                        'code' => 'COM000',
                        'transactionID' => $sale->getTransactionId(),
                    ],
                ];
                $sale->setTransactionStatus($comInfo);
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    $comInfo
                );
                $this->em->flush();
                $this->messageBus->dispatch(new CheckSaleMessage($saleId), [new DelayStamp(2000)]);
            } else {
                $comInfo = [
                    'status' => [
                        'message' => sprintf(
                            "action=Recharge, Message=%s",
                            $message
                        ),
                        'code' => 'COM000',
                        'transactionID' => $sale->getTransactionId(),
                    ],
                ];
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    $comInfo
                );
                $this->em->flush();
            }
            $this->logger->info($message);
        }
    }

    /**
     * @return void
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function unprocessed(): void
    {
        /** @var \App\Repository\CommunicationSaleInfoRepository $saleInfoRepo */
        $saleInfoRepo = $this->em->getRepository(CommunicationSaleInfo::class);
        $sales = $saleInfoRepo->getLastPending();
        foreach ($sales as $sale) {
            $this->checkStatusOrder($sale->getId());
        }
    }
}
