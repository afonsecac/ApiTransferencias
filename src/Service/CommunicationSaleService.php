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
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
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
use App\Provider\ProviderDispatchResolver;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Provider\TransactionStatus;
use App\Repository\BalanceOperationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Provider\ProviderAvailabilityService;
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
        private readonly ProviderAvailabilityService $availabilityService,
        private readonly PackageSalePriceResolver $salePriceResolver,
        private readonly CatalogVersionResolver $catalogVersionResolver,
        private readonly PackageCatalogResolver $packageCatalogResolver,
        private readonly ProviderDispatchResolver $dispatchResolver,
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

    /**
     * Único punto de despacho/diferido: encola el mensaje si el proveedor de
     * la venta está despachable (App\Service\Provider\ProviderAvailabilityService::canDispatchTo() —
     * switch global, credenciales+manual, y AUTO del ping), o la deja
     * PENDING para que la recuperación del proveedor (o dispatchPending())
     * la reencole más tarde.
     *
     * $messageFactory es un closure, no el mensaje ya construido: construirlo
     * de forma perezosa evita evaluar getId() cuando todavía puede ser null
     * (justo antes de un flush) en la rama que ni siquiera va a despachar.
     */
    private function dispatchOrDefer(CommunicationSaleInfo $sale, \Closure $messageFactory): void
    {
        $provider = $sale->getProvider() ?? CommunicationProviderEnum::ETECSA->value;
        $environmentType = $sale->getTenant()?->getEnvironment()?->getType();

        if ($this->availabilityService->canDispatchTo($provider, $environmentType)) {
            $this->messageBus->dispatch($messageFactory());
        } else {
            $this->logger->info("Communications dispatch disabled or provider unavailable: sale {$sale->getId()} saved, pending dispatch.");
        }
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
                $sale->setTransactionStatus(TransactionStatus::internal(
                    ProviderOutcomeEnum::REJECTED,
                    'INTERNAL_PROMOTION_EXPIRED',
                    'Promotion expired before activation',
                ));
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

        foreach ($toDispatch as $sale) {
            $this->dispatchOrDefer($sale, fn () => new SaleRechargeMessage($sale->getId()));
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
        /** @var \App\Repository\CommunicationPromotionsRepository $promotionRepo */
        $promotionRepo = $this->em->getRepository(CommunicationPromotions::class);
        $promotion = $promotionRepo->getFuturePromotionById(
            $reserveDto->getPromotionId(),
            $reserveDto->getPackageId()
        );
        if (is_null($promotion)) {
            throw new MyCurrentException('COM007', 'The promotion is not active to reserves');
        }

        // Reserve siempre trae promoción hoy (el DTO la exige) y las
        // promociones V2 todavía no existen (ver Fase 5 del plan) —
        // hasPromotion=true fuerza la rama legacy en admit() sin importar
        // CatalogVersionResolver::isV2(). El proveedor es propiedad del
        // producto, no de la cuenta — se valida y se congela ANTES de
        // construir la venta.
        $admission = $this->admit($user, $reserveDto->getPackageId(), 'recharge', forReserve: true, hasPromotion: true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setTenant($user);
        $recharge->setState(CommunicationStateEnum::RESERVED);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
        $recharge->setProvider($admission->provider);
        $recharge->setPromotionId($reserveDto->getPromotionId());
        $recharge->setPackageId($reserveDto->getPackageId());
        $recharge->setPhoneNumber($reserveDto->getPhoneNumber());
        $recharge->setClientTransactionId($reserveDto->getClientTransactionId());
        $this->applyAdmission($recharge, $admission);
        $recharge->setPromotion($promotion);
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );

        $recharge->setTransactionId($transactionId);
        $recharge->setAmount($admission->amount);
        $recharge->setCurrency($admission->currency);
        $recharge->getCalculatePrice();

        $this->em->beginTransaction();
        try {
            // Lock pesimista por cuenta + saldo-menos-reservado: cierra la
            // condición de carrera entre ventas concurrentes de la misma
            // cuenta. Ver docs/balance-check-architecture.md (Fase 1).
            if (!$this->balanceService->hasAvailableBalance($user, $admission->amount)) {
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

        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );

        // El proveedor es propiedad del producto, no de la cuenta — se
        // valida y se congela ANTES de construir la venta (ver admit()).
        $admission = $this->admit($user, $recharge->getPackageId(), 'recharge');

        $recharge->setTransactionId($transactionId);
        $this->applyAdmission($recharge, $admission);
        $recharge->setTenant($user);
        $recharge->setAmount($admission->amount);
        $recharge->setCurrency($admission->currency);
        $recharge->getCalculatePrice();
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
        $recharge->setProvider($admission->provider);

        $this->em->beginTransaction();
        try {
            // Lock pesimista por cuenta + saldo-menos-reservado: cierra la
            // condición de carrera entre ventas concurrentes de la misma
            // cuenta. Ver docs/balance-check-architecture.md (Fase 1).
            if (!$this->balanceService->hasAvailableBalance($user, $admission->amount)) {
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

        $this->dispatchOrDefer($recharge, fn () => new SaleRechargeMessage($recharge->getId()));

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
        $body = [];
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
                $saleRecharge->setTransactionStatus(TransactionStatus::internal(
                    ProviderOutcomeEnum::FAILED,
                    'INTERNAL_UNEXPECTED_USER',
                    'Unexpected user',
                ));
                $this->em->flush();

                return;
            }
            // Chequeo ANTES de claimForSending(): un mensaje que ya estaba en
            // RabbitMQ cuando el proveedor cayó no debe consumir la venta —
            // se deja en CREATED (sin reclamar) para que dispatchOrDefer()/
            // dispatchPending() la reencolen cuando el proveedor se recupere.
            if (!$this->availabilityService->canDispatchTo($saleRecharge->getProvider(), $user->getEnvironment()?->getType())) {
                $this->logger->info("Skipping recharge {$saleId}: provider not dispatchable right now, left pending for recovery.");

                return;
            }
            if (!$this->claimForSending($saleRecharge)) {
                $this->logger->info("Skipping recharge {$saleId}: already being processed (stateProcess={$saleRecharge->getStateProcess()})");
                return;
            }
            try {
                $balance = $this->balanceService->balance($user->getId());
                // V2 (catalogPackage persistido en admisión, ver admit()):
                // NUNCA re-derivar por $saleRecharge->getPackageId() — para
                // una venta V2 ese id es un CommunicationPackage.id, no un
                // CommunicationClientPackage.id (podría, en el peor caso,
                // coincidir por casualidad con un id no relacionado).
                $isV2Sale = $saleRecharge->getCatalogPackage() !== null;
                $package = null;
                if ($isV2Sale) {
                    $offer = $this->packageCatalogResolver->offerFor($saleRecharge->getCatalogPackage(), $user);
                    $currentAmount = ($offer !== null && $offer->source !== PackageOfferSourceEnum::UNAVAILABLE)
                        ? $offer->price
                        : null;
                } else {
                    /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
                    $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
                    $package = $clientPackageRepo->getPackageById(
                        $saleRecharge->getPackageId(),
                        $user
                    );
                    // Recheck asíncrono contra el mismo resolver que cobró en
                    // processRecharge() — $package->getAmount() a secas
                    // devolvería el snapshot crudo (0 para un paquete sin
                    // contrato materializado sin caché de precio), no el precio
                    // real vigente.
                    $currentAmount = $package !== null
                        ? $this->salePriceResolver->resolve($package, $user)->amount
                        : null;
                }
                if ($currentAmount === null || $balance->amount < $currentAmount) {
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $saleRecharge->setStateProcess(CommunicationStateEnum::REJECTED->value);
                    $saleRecharge->setTransactionStatus($currentAmount === null
                        ? TransactionStatus::internal(
                            ProviderOutcomeEnum::REJECTED,
                            'INTERNAL_PRICE_UNRESOLVED',
                            'Package price could not be resolved',
                        )
                        : TransactionStatus::internal(
                            ProviderOutcomeEnum::REJECTED,
                            'INTERNAL_INSUFFICIENT_BALANCE',
                            "The balance aren`t sufficient",
                            context: ['balance' => $balance->amount, 'required' => $currentAmount],
                        ));
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
                    $saleRecharge->setTransactionStatus(TransactionStatus::internal(
                        ProviderOutcomeEnum::FAILED,
                        'INTERNAL_UNEXPECTED_ENVIRONMENT',
                        'Unexpected environment',
                    ));
                    $this->em->flush();

                    return;
                }
                if ($isV2Sale) {
                    // Snapshot ya persistido en admit() (ver admitV2()) —
                    // nunca se re-deriva, y V2 todavía no soporta
                    // promociones (Fase 5), así que no hay sustitución de
                    // productCode que aplicar aquí.
                    $productCode = $saleRecharge->getDispatchExternalRef();
                    $destination = (object) [
                        'amount' => $saleRecharge->getDestinationAmount(),
                        'unit' => $saleRecharge->getDestinationCurrency(),
                    ];
                } else {
                    // externalRef, no packageId: packageId es la columna legacy
                    // (entero) que CommunicationCatalogSyncService colapsa a 0
                    // para cualquier proveedor cuyo id externo no sea numérico
                    // (CSQ usa "{articleId}-{amount}", ver
                    // CsqCommunicationProvider::fetchProducts()) — mandar
                    // packageId aquí le enviaría "0" al adaptador del proveedor
                    // como productExternalId, inservible para despachar nada.
                    // resolveProductExternalId() usa externalRef con fallback a
                    // packageId (productos creados a mano nunca setean
                    // externalRef) — ver su docblock.
                    $productCode = $this->resolveProductExternalId($package->resolveProduct());
                    if (!is_null($saleRecharge->getPromotionId())) {
                        /** @var \App\Repository\CommunicationPromotionsRepository $promotionRepo */
                        $promotionRepo = $this->em->getRepository(CommunicationPromotions::class);
                        $promotion = $promotionRepo->getActivePromotionById(
                            $saleRecharge->getPromotionId()
                        );
                        if (!is_null($promotion)) {
                            $productCode = $this->resolveProductExternalId($promotion->getProduct());
                        }
                        $saleRecharge->setPromotionId($saleRecharge->getPromotionId());
                        $saleRecharge->setPromotion($promotion);
                    } elseif ($package->getPromotionItems()->count() === 1) {
                        $promotion = $package->getPromotionItems()->first();
                        $saleRecharge->setPromotionId($promotion->getId());
                        $saleRecharge->setPromotion($promotion);
                        $productCode = $this->resolveProductExternalId($promotion?->getProduct());
                    }

                    $destination = (object)$package->getDestination();
                }

                // El proveedor se resuelve ANTES de aplicar cualquier
                // sustitución de sandbox: cada rama de abajo es una
                // convención de prueba propia de UN proveedor, no una regla
                // general de "entorno TEST" — mezclarlas rompe: forzar el
                // productCode fijo "100" de ETECSA contra DTOne le manda un
                // product_id inventado y lo rechaza como "Product is not
                // available in your account" (confirmado en vivo el
                // 2026-08-03: nunca era un problema de permisos de la
                // cuenta de DTOne).
                $provider = $this->providerResolver->resolveForSale($saleRecharge);

                $phoneLength = strlen($saleRecharge->getPhoneNumber());
                $checkPhone = substr($saleRecharge->getPhoneNumber(), $phoneLength - 2, $phoneLength);
                $phoneNumber = $saleRecharge->getPhoneNumber();
                if ($environment->getType() === 'TEST' && $provider === CommunicationProviderEnum::ETECSA) {
                    $phoneNumber = $checkPhone === "60" ? $this->parameters->get(
                        'app.phoneNumber'
                    ) : $saleRecharge->getPhoneNumber();
                    $productCode = "100";
                } elseif ($environment->getType() === 'TEST' && $provider === CommunicationProviderEnum::CSQ) {
                    // Confirmado en vivo el 2026-08-11: CSQ acepta el número
                    // dummy "53500000" en su sandbox TEST contra cualquier
                    // producto real (compra exitosa contra Cubacel/7854 con
                    // este número) — el número real del cliente no está
                    // autorizado ahí, siempre rechaza con resultcode 991. A
                    // diferencia de ETECSA, no hace falta forzar un
                    // productCode fijo: el producto real sí funciona.
                    $phoneNumber = $checkPhone === "60" ? $this->parameters->get(
                        'app.csqPhoneNumber'
                    ) : $saleRecharge->getPhoneNumber();
                } elseif ($environment->getType() === 'TEST' && $provider === CommunicationProviderEnum::DTONE) {
                    // Documentado por DTOne (https://developers.dtone.com/reference/sandbox):
                    // su sandbox NO usa un número dummy fijo — simula el
                    // resultado según los ÚLTIMOS 3 DÍGITOS del número de
                    // destino, sin importar el resto ("100"/"200"/"300" =
                    // COMPLETED sin PIN). A diferencia de ETECSA/CSQ, aquí
                    // se conserva el número real del cliente y solo se le
                    // reemplaza el sufijo — ni el productCode ni el resto
                    // del número cambian. Esto explica retroactivamente el
                    // DECLINED visto el 2026-08-11 contra un número
                    // terminado en "36" (sufijo no documentado, comportamiento
                    // indefinido del sandbox).
                    $phoneNumber = $checkPhone === "60"
                        ? substr($saleRecharge->getPhoneNumber(), 0, -3) . '100'
                        : $saleRecharge->getPhoneNumber();
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
                $dispatchEnvelope = TransactionStatus::fromDispatch($dispatchResult, $provider->value, ['request' => $body]);
                $saleRecharge->setTransactionStatus($dispatchEnvelope);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);

                if ($dispatchResult->outcome === ProviderOutcomeEnum::REJECTED) {
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $saleRecharge->getId(),
                        CommunicationStateEnum::REJECTED,
                        $dispatchEnvelope
                    );
                } elseif ($dispatchResult->outcome === ProviderOutcomeEnum::COMPLETED) {
                    // CSQ es síncrono (a diferencia de ETECSA/DTOne, que solo
                    // devuelven ACCEPTED en el dispatch y confirman después
                    // vía fetchRechargeStatus()): Purchase ya da el
                    // resultado final en la misma respuesta — ver
                    // CsqCommunicationProvider::recharge(). Finaliza aquí
                    // mismo, con la misma lógica que checkStatusOrder() usa
                    // cuando el POLL confirma COMPLETED (claim atómico +
                    // balance + histórico), porque ningún poll posterior va
                    // a llegar para esta venta.
                    // claimForCompleting() hace un UPDATE crudo + em->refresh($saleRecharge)
                    // si gana la carrera — refresh() descarta cualquier cambio en memoria
                    // hecho antes de esta llamada. Por eso transactionOrder/transactionStatus
                    // se fijan DESPUÉS, o el flush() de abajo no persistiría el envelope.
                    $claimed = $this->claimForCompleting($saleRecharge);
                    if ($dispatchResult->providerReference !== null) {
                        $saleRecharge->setTransactionOrder($dispatchResult->providerReference);
                    }
                    $saleRecharge->setTransactionStatus($dispatchEnvelope);
                    if ($claimed) {
                        try {
                            $this->balanceService->createSaleBalance($user, $saleRecharge);
                        } catch (\Exception $balanceEx) {
                            $this->logger->critical("BALANCE FAILED for sale {$saleRecharge->getId()}: " . $balanceEx->getMessage());
                        }
                        $this->historicalSaleService->createHistoricalCommunication(
                            $saleRecharge->getId(),
                            CommunicationStateEnum::COMPLETED,
                            $dispatchEnvelope
                        );
                    } else {
                        $this->logger->info("Sale {$saleId}: already completed by another worker, skipping balance.");
                    }
                }
                $this->em->flush();
                // Solo despachar check si el envío fue aceptado (ACCEPTED). Si el
                // resultado es UNKNOWN (timeout/error de transporte: no sabemos si
                // la petición llegó al proveedor) no se reprograma nada aquí — el
                // cron de pendientes (CheckStatusTask) la recogerá más tarde.
                // Jamás debe reintentarse el ENVÍO mismo tras un UNKNOWN: eso
                // podría cobrar dos veces la misma recarga. COMPLETED tampoco
                // reprograma nada: ya se finalizó arriba.
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
                // UNKNOWN, no FAILED: el state queda PENDING (nunca sabemos si
                // el proveedor llegó a procesar la operación tras esta
                // excepción) — internalPreserving() conserva el raw/reference
                // del proveedor si el dispatch sí llegó a intentarse (L582).
                $genericErrorEnvelope = TransactionStatus::internalPreserving(
                    $saleRecharge->getTransactionStatus(),
                    ProviderOutcomeEnum::UNKNOWN,
                    'INTERNAL_UNEXPECTED_ERROR',
                    'Unexpected error: ' . $ex->getMessage(),
                );
                $saleRecharge->setTransactionStatus($genericErrorEnvelope);
                $this->historicalSaleService->createHistoricalCommunication(
                    $saleId,
                    CommunicationStateEnum::PENDING,
                    $genericErrorEnvelope
                );
                if ($ex instanceof Exception\UniqueConstraintViolationException) {
                    if (strpos($ex->getPrevious()?->getMessage() ?? '', "unique_identification_client") !== false) {
                        $duplicateEnvelope = TransactionStatus::internal(
                            ProviderOutcomeEnum::REJECTED,
                            'INTERNAL_DUPLICATE_TRANSACTION',
                            'Duplicate transaction by customer',
                        );
                        $saleRecharge->setTransactionStatus($duplicateEnvelope);
                        $this->historicalSaleService->createHistoricalCommunication(
                            $saleId,
                            CommunicationStateEnum::REJECTED,
                            $duplicateEnvelope
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

        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSalePackage::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'02'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        // El proveedor es propiedad del producto, no de la cuenta — se
        // valida y se congela ANTES de construir la venta (ver admit()).
        $admission = $this->admit($user, $sale->getPackageId(), 'sale');

        $sale->setTransactionId($transactionId);
        $this->applyAdmission($sale, $admission);
        $sale->setTenant($user);
        $sale->setAmount($admission->amount);
        $sale->setCurrency($admission->currency);
        $sale->getCalculatePrice();
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);
        $sale->setProvider($admission->provider);

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
            if (!$this->balanceService->hasAvailableBalance($user, $admission->amount)) {
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

        $this->dispatchOrDefer($sale, fn () => new SalePackageMessage($sale->getId()));

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
        // Chequeo ANTES de claimForSending(): ver invokeRechargeCommunication()
        // para la misma justificación (no consumir un mensaje ya encolado si
        // el proveedor no es despachable ahora mismo).
        if (!$this->availabilityService->canDispatchTo($sale->getProvider(), $sale->getTenant()?->getEnvironment()?->getType())) {
            $this->logger->info("Skipping sale {$saleId}: provider not dispatchable right now, left pending for recovery.");

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
                $this->failSale($sale, 'Unexpected user', 'INTERNAL_UNEXPECTED_USER');
                return;
            }
            $commercialOffice = $sale->getCommercialOffice();
            if (is_null($commercialOffice)) {
                $this->failSale($sale, 'Missing commercial office', 'INTERNAL_MISSING_COMMERCIAL_OFFICE');
                return;
            }
            $nationality = $sale->getNationality();
            if (is_null($nationality)) {
                $this->failSale($sale, 'Missing nationality', 'INTERNAL_MISSING_NATIONALITY');
                return;
            }
            // V2 (dispatchProduct persistido en admisión, ver admitV2()):
            // getPackage() es null a propósito — el snapshot ya trae todo
            // lo que hace falta, sin volver a resolver nada.
            $isV2Sale = $sale->getCatalogPackage() !== null;
            $package = $sale->getPackage();
            if (!$isV2Sale && !$package instanceof CommunicationClientPackage) {
                $this->failSale($sale, 'Missing package', 'INTERNAL_MISSING_PACKAGE');
                return;
            }
            $officeComId = $commercialOffice->getComId();
            if ($isV2Sale) {
                $packageProductId = $sale->getDispatchExternalRef();
                $productKind = $sale->getDispatchProduct()?->getPackageType();
            } else {
                $resolvedProduct = $package->resolveProduct();
                // resolveProductExternalId(): externalRef con fallback a
                // packageId — ver su docblock.
                $packageProductId = $this->resolveProductExternalId($resolvedProduct);
                $productKind = $resolvedProduct?->getPackageType();
            }

            $provider = $this->providerResolver->resolveForSale($sale);
            $adapter = $this->providerRegistry->getFor($provider, PackageSaleProviderInterface::class);
            $context = $this->providerContextFactory->forSale($sale);
            $request = new PackageSaleRequest(
                transactionId: $transactionId,
                productExternalId: $packageProductId !== null ? (string) $packageProductId : '',
                productKind: $productKind,
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
            $sale->setTransactionStatus(TransactionStatus::fromDispatch($dispatchResult, $provider->value));
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
     * V2 Fase 4 — único punto de admisión de venta para
     * processReserve()/processRecharge()/executeSale(): bifurca por
     * CatalogVersionResolver::isV2(). Rama legacy: mismo código de siempre
     * (resolveAndGuardProvider() + PackageSalePriceResolver), sin cambio de
     * comportamiento. Rama V2: PackageCatalogResolver::offerForSale() +
     * ProviderDispatchResolver::select(), devolviendo el snapshot que
     * applyAdmission() persistirá en la venta.
     *
     * $hasPromotion=true fuerza SIEMPRE la rama legacy, sin importar
     * isV2(): las promociones V2 no existen todavía (Fase 5) y
     * processReserve() hoy exige promoción en el 100% de los casos (el DTO
     * la hace obligatoria) — este flag es lo que mantiene su rama V2 inerte
     * hasta que Fase 5 dé de alta el equivalente.
     *
     * @throws MyCurrentException
     */
    private function admit(
        Account $user,
        int $packageId,
        string $saleType,
        bool $forReserve = false,
        bool $hasPromotion = false,
    ): CommunicationSaleAdmission {
        if (!$hasPromotion && $this->catalogVersionResolver->isV2($user)) {
            return $this->admitV2($user, $packageId, $saleType);
        }

        return $this->admitLegacy($user, $packageId, $saleType, $forReserve);
    }

    /**
     * @throws MyCurrentException
     */
    private function admitLegacy(Account $user, int $packageId, string $saleType, bool $forReserve): CommunicationSaleAdmission
    {
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $package = $forReserve
            ? $clientPackageRepo->getPackageByIdForReserve($packageId, $user)
            : $clientPackageRepo->getPackageById($packageId, $user);
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }

        if ($saleType === 'recharge') {
            $this->assertRechargeableProduct($package);
        }

        $productProvider = $this->resolveAndGuardProvider($user, $package);

        // Único punto de precio: mismo resolver que usa el listado
        // (GET /communication/packages) — antes del rediseño de precios,
        // reserve/recharge cobraban ClientPackage.amount y executeSale()
        // cobraba priceClientPackage.amount, y podían divergir tras un
        // cambio de tarifa del proveedor. Ver PackageSalePriceResolver.
        $resolvedPrice = $this->salePriceResolver->resolveForSale($package, $user);

        return new CommunicationSaleAdmission(
            provider: $productProvider,
            amount: $resolvedPrice->amount,
            currency: $resolvedPrice->currency,
            legacyPackage: $package,
        );
    }

    /**
     * @throws MyCurrentException
     */
    private function admitV2(Account $user, int $packageId, string $saleType): CommunicationSaleAdmission
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($packageId);
        if ($package === null || !$this->isPackageWithinActiveWindow($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }

        // offerForSale() ya lanza MyCurrentException (409) si el paquete no
        // es visible para este cliente o no tiene precio resoluble — ver
        // PackageCatalogResolver.
        $offer = $this->packageCatalogResolver->offerForSale($package, $user);

        // select() ya lanza MyCurrentException (409 PACKAGE_NOT_DISPATCHABLE)
        // si ningún proveedor disponible de la prioridad del cliente cubre
        // la tupla — ver ProviderDispatchResolver.
        $dispatch = $this->dispatchResolver->select($user, $package, $saleType);

        return new CommunicationSaleAdmission(
            provider: $dispatch->provider->value,
            amount: $offer->price,
            currency: $offer->currency,
            catalogPackage: $package,
            dispatchProduct: $dispatch->product,
            dispatchExternalRef: $dispatch->externalRef,
            destinationAmount: $package->getDestinationAmount(),
            destinationCurrency: $package->getDestinationCurrency(),
        );
    }

    private function isPackageWithinActiveWindow(CommunicationPackage $package): bool
    {
        if (!$package->isActive()) {
            return false;
        }

        $now = new \DateTimeImmutable();
        if ($package->getActiveStartAt() !== null && $package->getActiveStartAt() > $now) {
            return false;
        }

        return $package->getActiveEndAt() === null || $package->getActiveEndAt() > $now;
    }

    /**
     * Vuelca el snapshot de admit() en la venta: el paquete legacy
     * (CommunicationClientPackage) o el snapshot V2 completo — nunca ambos.
     */
    private function applyAdmission(CommunicationSaleInfo $sale, CommunicationSaleAdmission $admission): void
    {
        if ($admission->legacyPackage !== null) {
            $sale->setPackage($admission->legacyPackage);

            return;
        }

        $sale->setCatalogPackage($admission->catalogPackage);
        $sale->setDispatchProduct($admission->dispatchProduct);
        $sale->setDispatchExternalRef($admission->dispatchExternalRef);
        $sale->setDestinationAmount($admission->destinationAmount);
        $sale->setDestinationCurrency($admission->destinationCurrency);
    }

    /**
     * El proveedor es propiedad del producto, no de la cuenta: se lee de
     * package.resolveProduct().provider (prioriza el contrato congelado si
     * lo hay, si no el producto directo — ver
     * CommunicationClientPackage::resolveProduct(), que reemplaza el acceso
     * directo a priceClientPackage desde este rediseño: un paquete SIN
     * contrato también debe poder resolver su proveedor) y se valida contra
     * ProviderResolver::allowedForClient() ANTES de admitir la venta. Esto
     * es lo que impide que el routing de un cliente (Fase 2) mande un
     * productCode de un proveedor a otro distinto — el error se detecta en
     * admisión, no como un fallo silencioso en el despacho.
     */
    private function resolveAndGuardProvider(Account $user, CommunicationClientPackage $package): string
    {
        $productProvider = $package->resolveProduct()?->getProvider()
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
        $packageType = $package->resolveProduct()?->getPackageType();

        if ($packageType !== null && str_contains($packageType, 'PIN_PURCHASE')) {
            throw new MyCurrentException(
                'PRODUCT_REQUIRES_PACKAGE_SALE',
                'Este producto se canjea por código y solo puede venderse como paquete, no como recarga',
                Response::HTTP_CONFLICT,
            );
        }
    }

    /**
     * externalRef es la clave canónica ante el proveedor (para ETECSA/DTOne
     * coincide con packageId en forma de string). Fallback a packageId
     * porque los productos creados a mano vía POST /products
     * (CommunicationProductService::createProduct()) nunca setean
     * externalRef — solo los sincronizados vía
     * CommunicationCatalogSyncService lo hacen — así que quedarían con
     * productExternalId vacío sin este fallback.
     */
    private function resolveProductExternalId(?CommunicationProduct $product): ?string
    {
        if ($product === null) {
            return null;
        }

        return $product->getExternalRef() !== '' ? $product->getExternalRef() : (string) $product->getPackageId();
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

    private function failSale(CommunicationSaleInfo $sale, string $reason, string $code = 'INTERNAL_SALE_PRECONDITION'): void
    {
        $sale->setState(CommunicationStateEnum::FAILED);
        $sale->setStateProcess(CommunicationStateEnum::FAILED->value);
        $sale->setTransactionStatus(TransactionStatus::internal(ProviderOutcomeEnum::FAILED, $code, $reason));
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

            $statusEnvelope = TransactionStatus::fromStatus($statusResult, $provider->value);
            $sale->setTransactionStatus($statusEnvelope);

            if ($statusResult->outcome === ProviderOutcomeEnum::COMPLETED) {
                // Atomic claim: only one concurrent worker proceeds to create the balance.
                // claimForCompleting() hace un UPDATE crudo + em->refresh($sale) si gana la
                // carrera, lo que descarta cualquier cambio en memoria hecho antes de esta
                // llamada — por eso transactionStatus/transactionOrder se fijan DESPUÉS.
                $claimed = $this->claimForCompleting($sale);
                if ($statusResult->providerReference !== null) {
                    $sale->setTransactionOrder($statusResult->providerReference);
                }
                $sale->setTransactionStatus($statusEnvelope);

                if (!$claimed) {
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
                    $statusEnvelope
                );
            } elseif ($statusResult->outcome === ProviderOutcomeEnum::REJECTED) {
                $sale->setState(CommunicationStateEnum::REJECTED);
                $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                if ($statusResult->recordHistory) {
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::REJECTED,
                        $statusEnvelope
                    );
                }
            } elseif ($statusResult->recordHistory) {
                // ACCEPTED/PENDING/RETRYABLE/UNKNOWN se mapean todos aquí: la venta
                // sigue PENDING (ya lo estaba), solo se registra el histórico.
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    $statusResult->recordHistoryWithoutData ? [] : $statusEnvelope
                );
            }
            $this->em->flush();
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->logger->error($message);
            if ($e->getCode() === 404) {
                $currentStatus = $sale->getTransactionStatus();
                $retryCount = TransactionStatus::retryCountOf($currentStatus);

                if ($sale instanceof CommunicationSaleRecharge && $retryCount < 3) {
                    $now = new \DateTimeImmutable();
                    $lastRetryAtRaw = TransactionStatus::lastRetryAtOf($currentStatus);
                    $lastRetryAt = $lastRetryAtRaw !== null ? new \DateTimeImmutable($lastRetryAtRaw) : null;
                    $referenceTime = $lastRetryAt ?? $sale->getCreatedAt();
                    $secondsElapsed = $now->getTimestamp() - $referenceTime->getTimestamp();

                    if ($secondsElapsed >= 4 * 3600) {
                        $nextRetryCount = $retryCount + 1;
                        $sale->setTransactionStatus(TransactionStatus::withRetry(
                            $currentStatus,
                            ProviderOutcomeEnum::RETRYABLE,
                            'INTERNAL_GATEWAY_NOT_FOUND_RETRY',
                            'Not found in ApiComm, resending',
                            ['count' => $nextRetryCount, 'lastAttemptAt' => $now->format(\DateTimeInterface::ATOM)],
                        ));
                        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);
                        $this->em->flush();
                        $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
                        $this->logger->info("Sale {$saleId}: not found in ApiComm, resending (attempt {$nextRetryCount})");
                    }
                } else {
                    $now = new \DateTimeImmutable();
                    $reason = $sale instanceof CommunicationSaleRecharge
                        ? 'Not found in ApiComm after max retries'
                        : 'Not found in ApiComm';
                    $retryEnvelope = TransactionStatus::withRetry(
                        $currentStatus,
                        ProviderOutcomeEnum::REJECTED,
                        'INTERNAL_GATEWAY_MISSING',
                        $reason,
                        [
                            'count' => $retryCount,
                            'gatewayMissing' => true,
                            'markedRejectedAt' => $now->format(\DateTimeInterface::ATOM),
                            'reason' => $reason,
                        ],
                    );
                    $sale->setState(CommunicationStateEnum::REJECTED);
                    $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                    $sale->setTransactionStatus($retryEnvelope);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::REJECTED,
                        $retryEnvelope
                    );
                    $this->em->flush();
                    $this->logger->critical("Sale {$saleId}: not found in ApiComm, marked REJECTED for manual review.");
                }
            } elseif ($e->getCode() === 400) {
                // internalPreserving(): un 400 durante el polling no debe
                // borrar el raw del poll exitoso anterior (bug real cerrado
                // de paso al homologar, ver docs/transaction-status-v2.md).
                $httpErrorEnvelope = TransactionStatus::internalPreserving(
                    $sale->getTransactionStatus(),
                    ProviderOutcomeEnum::PENDING,
                    'INTERNAL_PROVIDER_HTTP_400',
                    'La orden aun esta en procesamiento',
                );
                $sale->setTransactionStatus($httpErrorEnvelope);
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    $httpErrorEnvelope
                );
                $this->em->flush();
                $this->messageBus->dispatch(new CheckSaleMessage($saleId), [new DelayStamp(2000)]);
            } else {
                // Solo histórico, no transactionStatus — mismo comportamiento
                // que antes de homologar (el catch genérico nunca tocó la
                // columna, solo el 404 y el 400 lo hacían).
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::PENDING,
                    TransactionStatus::internal(ProviderOutcomeEnum::UNKNOWN, 'INTERNAL_STATUS_QUERY_ERROR', $message)
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
