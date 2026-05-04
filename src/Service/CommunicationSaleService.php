<?php

namespace App\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
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
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Message\SalePackageMessage;
use App\Message\SaleRechargeMessage;
use App\Repository\BalanceOperationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CommunicationSaleService extends CommonService
{
    const ETECSA_INFO_ERROR = [

        '100' => 'No se especificaron productos para la venta',
        '101' => 'Se enviÃ³ una Recarga junto a compra de artÃ­culos solamente.',
        '102' => 'Se enviÃ³ mÃ¡s de una ActivaciÃ³n en la misma transacciÃ³n.',
        '103' => 'Se enviÃ³ mÃ¡s de una Recarga en la misma transacciÃ³n.',
        '104' => 'Los datos del cliente no son correctos.',
        '105' => 'Monto de la recarga asociada a una ActivaciÃ³n no permitido',
        '107' => 'El identificador de la transacciÃ³n no es vÃ¡lido',
        '108' => 'Monto de la recarga no permitido',
        '109' => 'El paquete especificado no estÃ¡ habilitado',
        '110' => 'El paquete especificado no existe',
        '111' => 'No fue especificado un paquete para la venta',
        '112' => 'Los datos para la modificacion de la venta no son vÃ¡lidos',
        '151' => 'El numero de telefono no existe',
        '152' => 'Servicio celular del cliente en estado no vÃ¡lido para ser recargado',
        '153' => 'Tipo de servicio celular del cliente no vÃ¡lido para ser recargado',
        '198' => 'CombinaciÃ³n de productos no vÃ¡lida',
        '199' => 'Datos de la venta incorrectos',
        '200' => 'La venta de productos no pudo ejecutarse satisfactoriamente',
        '201' => 'La ActivaciÃ³n no pudo ejecutarse satisfactoriamente',
        '202' => 'La Recarga no pudo ejecutarse satisfactoriamente.',
        '203' => 'La venta de Terminales no pudo ejecutarse satisfactoriamente',
        '204' => 'La venta de Accesorios no pudo ejecutarse satisfactoriamente',
        '205' => 'No se pudo cambiar la contraseÃ±a de usuario',
        '206' => 'La venta del paquete no pudo ejecutarse satisfactoriamente',
        '207' => 'No se pudieron modificar los datos de venta',
        '208' => 'No se pudo cancelar la venta',
        '209' => 'No se pudo realizar el chequeo de los datos de la activaciÃ³n de lÃ­nea celular',
        '300' => 'No se pudo obtener el estado de la venta',
        '301' => 'No se pudieron obtener los datos de la venta',
        '302' => 'No se pudieron obtener los datos de las ventas',
        '303' => 'No se pudieron obtener los datos de las oficinas comerciales',
        '304' => 'No se pudieron obtener los privilegios del usuario',
        '305' => 'No se pudieron obtener los datos de las provincias',
        '306' => 'No se pudieron obtener los datos del distribuidor',
        '307' => 'No se pudieron obtener los paquetes para la venta',
        '308' => 'No se pudieron obtener los elementos de paquetes',
        '309' => 'No se pudieron obtener los datos de los tipos de identificaciÃ³n',
        '310' => 'No se pudieron obtener los datos de los Municipios',
        '311' => 'No se pudieron obtener los datos de las Nacionalidades',
        '901' => 'El distribuidor no tiene permitida la venta de Activaciones',
        '902' => 'El distribuidor no tiene permitida la venta de Recargas',
        '903' => 'El distribuidor no tiene permitida la venta de Terminales y Accesorios',
        '904' => 'El distribuidor no tiene permitida la venta de Activaciones Temportales TURISTA',
        '905' => 'El distribuidor no tiene permitida la venta de Recursos TURISTA',
        '-1' => 'Su transaccion se esta procesando',
        '-2' => 'Su orden aun se esta procesando',
        '-3' => 'Ha ocurrido una falla durante el proceso de procesamiento de la recarga. Pronto nos pondremos en contacto.',
    ];

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
     * @param \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient
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
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigureSequenceService $configureSequence,
        private readonly MessageBusInterface $messageBus,
        private readonly HistoricalSaleService $historicalSaleService,
        private readonly BalanceService $balanceService,
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

        foreach ($toDispatch as $sale) {
            $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
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
        $balance = $this->balanceService->balance($user->getId());
        if ($balance->amount < $package->getAmount()) {
            throw new MyCurrentException('COM001', 'Insufficient balance');
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


        $recharge = new CommunicationSaleRecharge();
        $recharge->setTenant($user);
        $recharge->setState(CommunicationStateEnum::RESERVED);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);
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
        try {
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::RESERVED);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();
        } catch (\Exception $e) {
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
        $balance = $this->balanceService->balance($user->getId());
        if ($balance->amount < $package->getAmount()) {
            throw new MyCurrentException('COM001', 'Insufficient balance');
        }

        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        $recharge->setTransactionId($transactionId);
        $recharge->setPackage($package);
        $recharge->setTenant($user);
        $recharge->setAmount($package->getAmount());
        $recharge->setCurrency($package->getCurrency());
        $recharge->getCalculatePrice();
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess(CommunicationStateEnum::CREATED->value);

        try {
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();

            $this->messageBus->dispatch(
                new SaleRechargeMessage(
                    $recharge->getId()
                )
            );
        } catch (\Exception $ex) {
            if (str_contains($ex->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException(
                    "103",
                    mb_convert_encoding(self::ETECSA_INFO_ERROR['103'], 'ISO-8859-1', 'UTF-8')
                );
            }
            if (str_contains($ex->getMessage(), "unique_transaction_id")) {
                throw new MyCurrentException(
                    "103",
                    mb_convert_encoding(self::ETECSA_INFO_ERROR['103'], 'ISO-8859-1', 'UTF-8')
                );
            }
            $this->logger->error("Recharge persist failed: " . $ex->getMessage());
            throw $ex;
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
        $recharge?->setState(CommunicationStateEnum::PENDING);
        $this->em->flush();
        $this->messageBus->dispatch(
            new SaleRechargeMessage(
                $saleId
            )
        );
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
                $urlRecharge = $environment->getBasePath().'/sale/recharge';
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

                $phoneLength = strlen($saleRecharge->getPhoneNumber());
                $checkPhone = substr($saleRecharge->getPhoneNumber(), $phoneLength - 2, $phoneLength);
                $phoneNumber = $saleRecharge->getPhoneNumber();
                if ($environment->getType() === 'TEST') {
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

                $rechargeResponse = $this->httpClient->request(
                    'POST',
                    $urlRecharge,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                        'body' => $this->serializer->serialize($body, 'json', []),
                    ]
                );
                $rechargeInfo = $rechargeResponse->toArray();
                $saleRecharge->setTransactionStatus($rechargeInfo);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
                $rechargeResult = (object)((object)$rechargeInfo)->result;
                if ((int)$rechargeResult->code !== -1) {
                    $code = $rechargeResult->code;
                    $errMsg = null;
                    if (is_numeric($code)) {
                        $errMsg = self::ETECSA_INFO_ERROR[$code];
                    }
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $saleRecharge->getId(),
                        CommunicationStateEnum::REJECTED
                    );


                    if ($errMsg) {
                        $errMsg = mb_convert_encoding($errMsg, 'ISO-8859-1', 'UTF-8');
                    }
                    $comInfo = [
                        'error' => [
                            'message' => sprintf(
                                "action=Recharge, Message=%s",
                                $errMsg ?? 'Unexpected message during the sale'
                            ),
                            'orderID' => $orderId,
                            'code' => sprintf(
                                "COM%s",
                                $code
                            ),
                            'transactionID' => $saleRecharge->getTransactionId(),
                            'body' => $body,
                            'bodyCheck' => $bodyCheck,
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($comInfo);
                }
                $this->em->flush();
                // Solo despachar check si la venta sigue pendiente (no si fue rechazada)
                if ($saleRecharge->getState() === CommunicationStateEnum::PENDING) {
                    $this->messageBus->dispatch(new CheckSaleMessage($saleId));
                }
            } catch (ClientExceptionInterface|TimeoutException $exc) {
                // Timeout/error de cliente: la petición pudo haber llegado a ETECSA.
                // NO resetear stateProcess a CREATED para evitar reenvío.
                $saleRecharge->setState(CommunicationStateEnum::PENDING);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
                $comInfo = [
                    'error' => [
                        'message' => sprintf(
                            "action=Recharge, Message=%s",
                            'The provider server no response'
                        ),
                        'orderID' => $orderId,
                        'code' => 'COM004',
                        'transactionID' => $saleRecharge->getTransactionId(),
                        'body' => $body,
                        'bodyCheck' => $bodyCheck,
                    ],
                ];
                $this->historicalSaleService->createHistoricalCommunication(
                    $saleId,
                    CommunicationStateEnum::PENDING,
                    $comInfo
                );
                $saleRecharge->setTransactionStatus($comInfo);
            } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $exc) {
                // Error de red/servidor: la petición pudo haber llegado a ETECSA.
                // NO resetear stateProcess a CREATED para evitar reenvío.
                $saleRecharge->setState(CommunicationStateEnum::PENDING);
                $saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
                $comInfo = [
                    'error' => [
                        'message' => sprintf(
                            "action=Recharge, Message=%s",
                            'Unexpected error during sale'
                        ),
                        'code' => 'COM000',
                        'transactionID' => $saleRecharge->getTransactionId(),
                    ],
                ];
                $this->historicalSaleService->createHistoricalCommunication(
                    $saleId,
                    CommunicationStateEnum::PENDING,
                    $comInfo
                );
                $saleRecharge->setTransactionStatus($comInfo);
            } catch (\Exception $ex) {
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
        $balance = $this->balanceService->balance($user->getId());
        /** @var \App\Repository\CommunicationClientPackageRepository $clientPackageRepo */
        $clientPackageRepo = $this->em->getRepository(CommunicationClientPackage::class);
        $package = $clientPackageRepo->getPackageById(
            $sale->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        if ($balance->amount < $package->getPriceClientPackage()?->getAmount()) {
            throw new MyCurrentException('COM001', 'Insufficient balance');
        }
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSalePackage::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'02'.str_pad(
                (string) $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        $sale->setTransactionId($transactionId);
        $sale->setPackage($package);
        $sale->setTenant($user);
        $sale->setAmount($package->getPriceClientPackage()?->getAmount());
        $sale->setCurrency($package->getPriceClientPackage()?->getCurrency());
        $sale->getCalculatePrice();
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setStateProcess(CommunicationStateEnum::CREATED->value);

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

        try {
            $this->em->persist($sale);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($sale);
            $this->em->persist($comHistoric);
            $this->em->flush();

            $this->messageBus->dispatch(new SalePackageMessage($sale->getId()));
        }  catch (\Exception $ex) {
            if (str_contains($ex->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException(
                    "102",
                    mb_convert_encoding(self::ETECSA_INFO_ERROR['102'], 'ISO-8859-1', 'UTF-8')
                );
            }
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
            $urlSale = $user->getEnvironment()?->getBasePath().'/sale/package';

            $body = [
                'client' => [
                    'id' => $sale->getIdentificationNumber(),
                    'name' => $sale->getName(),
                    'identificationType' => $sale->getIdentificationType() ?? 1,
                    'arrivalDate' => $sale->getArrivalAt() ? $sale->getArrivalAt()->format('Y-m-d') : null,
                    'isAirport' => $commercialOffice->isIsAirport(),
                    'commercialOfficeId' => $commercialOffice->getComId(),
                    'provinceId' => $commercialOffice->getProvince()?->getComId(),
                    'nationality' => $nationality->getComId(),
                ],
                'packageInfo' => [
                    'id' => $package->getPriceClientPackage()?->getProduct()?->getPackageId(),
                    'packageType' => $package->getPriceClientPackage()?->getProduct()?->getPackageType(),
                ],
                'transactionId' => $transactionId,
                'environment' => $user->getEnvironment()?->getType(),
            ];

            $saleResponse = $this->httpClient->request(
                'POST',
                $urlSale,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->serialize($body, 'json', []),
                ]
            );

            $saleInfo = $saleResponse->toArray();
            $saleResult = (object)((object)$saleInfo)->result;
            $code = null;
            if (property_exists($saleResult, 'code')) {
                $code = (int) $saleResult->code;
            }
            if ($code === -1) {
                $sale->setState(CommunicationStateEnum::PENDING);
            } elseif (isset($code) && $code > 0) {
                $sale->setState(CommunicationStateEnum::FAILED);
            }
            $sale->setTransactionStatus($saleInfo);
            $sale->setStateProcess(CommunicationStateEnum::PENDING->value);
            $this->em->flush();
            $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()));
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
    }

    public function checkSaleInfo(int $saleId): CommunicationSaleInfo|null
    {
        $user = $this->security->getUser();
        $communicationSale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        try {
            if (is_null($communicationSale)) {
                return null;
            }
            if (!$user instanceof Account || $user->getId() !== $communicationSale->getTenant()?->getId()) {
                return null;
            }
            $this->checkStatusOrder($saleId);
            $communicationSale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        } catch (\Exception $exc) {
            $message = $exc->getMessage();
            $this->logger->info($message);
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
        $this->checkStatusOrder($saleId, true);
    }

    /**
     * @param int $saleId
     * @param bool|null $isProcess
     * @return void
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function checkStatusOrder(int $saleId, ?bool $isProcess = null): void
    {
        sleep(2);
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
        $url = $tenant->getEnvironment()?->getBasePath().'/sale/sale-info';

        $body = [
            'environment' => $tenant->getEnvironment()?->getType(),
            'transactionId' => $sale->getTransactionId(),
        ];

        $rechargeResponse = null;
        try {
            $rechargeResponse = $this->httpClient->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->serialize($body, 'json'),
                ]
            );

            $response = $rechargeResponse->toArray();
            $responseInfo = (object)$response;
            $statusResponse = strtoupper($responseInfo->status);
            $result = isset($responseInfo->result) ? (object)$responseInfo->result : null;
            $fullResponse = isset($responseInfo->fullResponse) ? (object) $responseInfo->fullResponse : null;
            $isTrue = false;
            if ($fullResponse !== null) {
                $fullResponseObj = isset($fullResponse->SaleRecharge) ? (object) $fullResponse->SaleRecharge : null;
                if ($fullResponseObj !== null) {
                    $isTrue = $statusResponse === "COMPLETED" && $fullResponseObj->RechargeStateCode === "OK";
                }
            }
            $sale->setTransactionStatus($response);
            if ($isTrue || (property_exists($responseInfo, 'orderId') && isset($responseInfo->orderId))) {
                // Evitar procesar si ya fue completada (check concurrente)
                $this->em->refresh($sale);
                if ($sale->getState() === CommunicationStateEnum::COMPLETED) {
                    return;
                }
                if (property_exists($responseInfo, 'orderId') && isset($responseInfo->orderId)) {
                    $orderId = $responseInfo->orderId;
                    $sale->setTransactionOrder($orderId);
                }
                if ($sale instanceof CommunicationSaleRecharge) {
                    $sale->setState(CommunicationStateEnum::COMPLETED);
                    $sale->setStateProcess(CommunicationStateEnum::COMPLETED->value);
                } elseif (isset($responseInfo->fullResponse['Sale']) && $sale instanceof CommunicationSalePackage) {
                    $sale->setState(CommunicationStateEnum::COMPLETED);
                    $sale->setStateProcess(CommunicationStateEnum::COMPLETED->value);
                } else {
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
                    $response
                );
            } elseif (!is_null($result) && isset($responseInfo->status) && $result->valueOk && $responseInfo->status === CommunicationStateEnum::REJECTED->value) {
                $sale->setState(CommunicationStateEnum::REJECTED);
                $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::REJECTED,
                    $response
                );
            } elseif (!is_null($result) && !$result->valueOk) {
                if (!is_null($result->code)) {
                    $message = self::ETECSA_INFO_ERROR[$result->code];
                    $response['result']['message'] = mb_convert_encoding($message, 'ISO-8859-1', 'UTF-8');
                    $sale->setTransactionStatus($response);
                    if (in_array($result->code, ['151', '152', '153', '198', '199', '200'], true)) {
                        $sale->setState(CommunicationStateEnum::REJECTED);
                        $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                        $this->historicalSaleService->createHistoricalCommunication(
                            $sale->getId(),
                            CommunicationStateEnum::REJECTED,
                            $response
                        );
                    } elseif ((int)$result->code !== -1) {
                        $sale->setState(CommunicationStateEnum::PENDING);
                        $sale->setStateProcess(CommunicationStateEnum::PENDING->value);
                        $this->historicalSaleService->createHistoricalCommunication(
                            $sale->getId(),
                            CommunicationStateEnum::PENDING,
                            $response
                        );
                    }
                } else {
                    $sale->setState(CommunicationStateEnum::REJECTED);
                    $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::PENDING,
                        $response
                    );
                }
            } else {
                if (is_null($result)) {
                    $sale->setState(CommunicationStateEnum::PENDING);
                    $sale->setStateProcess(CommunicationStateEnum::PENDING->value);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::PENDING
                    );
                }
            }
            $this->em->flush();
        } catch (\Exception $e) {
            $infoResponse = $rechargeResponse !== null ? json_decode($rechargeResponse->getContent(false), true) : [];
            $message = $e->getMessage();
            $this->logger->error($rechargeResponse !== null ? $rechargeResponse->getContent(false) : $message, is_array($infoResponse) ? $infoResponse : []);
            if ($e instanceof ClientException && $e->getCode() === 404 && $isProcess) {
                // TO-DO: Recheck info to process
                // $this->tryAgainWithTransaction($saleId);
                $sale->setStateProcess(CommunicationStateEnum::FAILED->value);
                $this->em->flush();
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
                $this->messageBus->dispatch(new CheckSaleMessage($saleId));
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
            $this->checkStatusOrder($sale->getId(), true);
        }
    }
}
