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
     * @param \App\Repository\BalanceOperationRepository $balanceRepository
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
        $package = $this->em->getRepository(CommunicationClientPackage::class)->getPackageById(
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
        $promotion = $this->em->getRepository(CommunicationPromotions::class)->getFuturePromotionById(
            $reserveDto->getPromotionId(),
            $reserveDto->getPackageId()
        );
        if (is_null($promotion)) {
            throw new MyCurrentException('COM007', 'The promotion is not active to reserves');
        }


        $recharge = new CommunicationSaleRecharge();
        $recharge->setTenant($user);
        $recharge->setState(CommunicationStateEnum::RESERVED);
        $recharge->setPromotionId($reserveDto->getPromotionId());
        $recharge->setPackageId($reserveDto->getPackageId());
        $recharge->setPhoneNumber($reserveDto->getPhoneNumber());
        $recharge->setClientTransactionId($reserveDto->getClientTransactionId());
        $recharge->setPackage($package);
        $recharge->setPromotion($promotion);
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSaleRecharge::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'01'.str_pad(
                $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        $recharge->setTransactionId($transactionId);
        $recharge->setAmount($package->getAmount());
        $recharge->setCurrency($package->getCurrency());
        try {
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "unique_identification_client") >= 0) {
                throw new MyCurrentException('COM005', 'Duplicate transaction by customer');
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
        $package = $this->em->getRepository(CommunicationClientPackage::class)->getPackageById(
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
                $lastSequence,
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

        try {
            $this->em->persist($recharge);
            $comHistoric = new CommunicationSaleHistory();
            $comHistoric->setState(CommunicationStateEnum::PENDING);
            $comHistoric->setSale($recharge);
            $this->em->persist($comHistoric);
            $this->em->flush();

            $this->messageBus->dispatch(
                new SaleRechargeMessage(
                    $recharge->getId(),
                    $recharge
                )
            );
        } catch (\Exception $ex) {
            if (str_contains($ex->getMessage(), "unique_identification_client")) {
                throw new MyCurrentException(
                    "103",
                    mb_convert_encoding(self::ETECSA_INFO_ERROR['103'], 'ISO-8859-1', 'UTF-8')
                );
            }
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
                $saleId,
                $recharge
            )
        );
    }

    /**
     * @param \App\Entity\CommunicationSaleRecharge $recharge
     * @param int $saleId
     * @return void
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function invokeRechargeCommunication(CommunicationSaleRecharge $recharge, int $saleId): void
    {
        $orderId = null;
        $body = [];
        $bodyCheck = [];
        $saleRecharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
        if (is_null($saleRecharge)) {
            return;
        }
        if ($saleRecharge instanceof CommunicationSaleRecharge) {
            $user = $saleRecharge->getTenant();
            if (!$user instanceof Account) {
                $saleRecharge->setState(CommunicationStateEnum::FAILED);
                $rechargeInfo = [
                    'result' => [
                        'message' => 'Unexpected user',
                    ],
                ];
                $saleRecharge->setTransactionStatus($rechargeInfo);
                $this->em->flush();

                return;
            }
            try {
                $balance = $this->balanceService->balance($user->getId());
                $package = $this->em->getRepository(CommunicationClientPackage::class)->getPackageById(
                    $recharge->getPackageId(),
                    $user
                );
                if ($balance->amount < $package?->getAmount()) {
                    $saleRecharge->setState(CommunicationStateEnum::REJECTED);
                    $rechargeInfo = [
                        'result' => [
                            'message' => 'The balance aren`t sufficient',
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($rechargeInfo);
                    $this->em->flush();

                    return;

                }
                $environment = $this->em->getRepository(Environment::class)->find($user->getEnvironment()?->getId());
                if (is_null($environment)) {
                    $saleRecharge->setState(CommunicationStateEnum::FAILED);
                    $rechargeInfo = [
                        'result' => [
                            'message' => 'Unexpected environment',
                        ],
                    ];
                    $saleRecharge->setTransactionStatus($rechargeInfo);
                    $this->em->flush();

                    return;
                }
                $urlRecharge = $environment?->getBasePath().'/sale/recharge';
                $productCode = $package?->getPriceClientPackage()?->getProduct()?->getPackageId();
                if (!is_null($recharge->getPromotionId())) {
                    $promotion = $this->em->getRepository(CommunicationPromotions::class)->getActivePromotionById(
                        $recharge->getPromotionId()
                    );
                    if (!is_null($promotion)) {
                        $productCode = $promotion->getProduct()?->getPackageId();
                    }
                    $saleRecharge->setPromotionId($recharge->getPromotionId());
                    $saleRecharge->setPromotion($promotion);
                } elseif ($package?->getPromotionItems()->count() === 1) {
                    $promotion = $package?->getPromotionItems()->first();
                    $saleRecharge->setPromotionId($promotion->getId());
                    $saleRecharge->setPromotion($promotion);
                    $productCode = $promotion?->getProduct()?->getPackageId();
                }

                $destination = (object)$package?->getDestination();

                $phoneLength = strlen($recharge->getPhoneNumber());
                $checkPhone = substr($recharge->getPhoneNumber(), $phoneLength - 2, $phoneLength);
                $phoneNumber = $recharge->getPhoneNumber();
                if ($environment?->getType() === 'TEST') {
                    $phoneNumber = $checkPhone === "60" ? $this->parameters->get(
                        'app.phoneNumber'
                    ) : $recharge->getPhoneNumber();
                    $productCode = "100";
                }

                $body = [
                    'phoneNumber' => $phoneNumber,
                    'productCode' => $productCode,
                    'productPrice' => round($destination->amount, 2),
                    'transactionId' => $saleRecharge->getTransactionId(),
                    'environment' => $environment?->getType(),
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
                $rechargeResult = (object)((object)$rechargeInfo)->result;
                $txStatus = (boolean)$rechargeResult->valueOk;
                if ($txStatus) {
                    try {
                        $orderId = ((object)$rechargeInfo)->orderId;
                    } catch (\Exception $e) {
                        $orderId = null;
                    }

                    $bodyCheck = [
                        'orderId' => $orderId,
                        'transactionId' => $saleRecharge->getTransactionId(),
                        'environment' => $environment?->getType(),
                    ];
                    $saleRecharge->setState(CommunicationStateEnum::COMPLETED);
                    $comInfo = [
                        'info' => $rechargeInfo,
                    ];
                    $saleRecharge->setTransactionStatus($comInfo);

                    $balanceOperation = new BalanceOperation();
                    $balanceOperation->setTenant($user);
                    $balanceOperation->setAmount($package?->getAmount());
                    $balanceOperation->setCurrency($package?->getCurrency());
                    $balanceOperation->setState('COMPLETED');
                    $balanceOperation->setOperationType('DEBIT');
                    $balanceOperation->getCalculateTotal();
                    $balanceOperation->setTotalAmount($balanceOperation->getTotalAmount() * -1);
                    $balanceOperation->setTotalCurrency($package?->getCurrency());
                    $balanceOperation->setCommunicationSale($recharge);
                    $this->em->persist($balanceOperation);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $recharge->getId(),
                        CommunicationStateEnum::COMPLETED
                    );
                } elseif ((int)$rechargeResult->code !== -1) {
                    $code = $rechargeResult->code;
                    $errMsg = null;
                    if (is_numeric($code)) {
                        $errMsg = self::ETECSA_INFO_ERROR[$code];
                    }
                    $recharge->setState(CommunicationStateEnum::REJECTED);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $recharge->getId(),
                        CommunicationStateEnum::REJECTED
                    );


                    if ($errMsg) {
                        $errMsg = mb_convert_encoding($errMsg, 'ISO-8859-1', 'UTF-8');
                    }
                    $comInfo = [
                        'error' => [
                            'message' => sprintf(
                                "action=Recharge, Message=%s",
                                $errMsg ?? 'Unexpected message'
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
                $this->messageBus->dispatch(new CheckSaleMessage($saleId));
            } catch (ClientExceptionInterface|TimeoutException $exc) {
                $saleRecharge->setState(CommunicationStateEnum::FAILED);
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
                    $recharge->getId(),
                    CommunicationStateEnum::FAILED,
                    $comInfo
                );
                $saleRecharge->setTransactionStatus($comInfo);
            } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $exc) {
                $saleRecharge->setState(CommunicationStateEnum::FAILED);
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
                $this->historicalSaleService->createHistoricalCommunication(
                    $recharge->getId(),
                    CommunicationStateEnum::FAILED,
                    $comInfo
                );
                $saleRecharge->setTransactionStatus($comInfo);
            } catch (\Exception $ex) {
                $saleRecharge->setState(CommunicationStateEnum::FAILED);
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
                    $recharge->getId(),
                    CommunicationStateEnum::FAILED
                );
                if ($ex instanceof Exception\UniqueConstraintViolationException) {
                    if (strpos($ex->getPrevious()?->getMessage(), "unique_identification_client") >= 0) {
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
        } else {
            throw new MyCurrentException(151, 'The sale information no longer exists.');
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
        $package = $this->em->getRepository(CommunicationClientPackage::class)->getPackageById(
            $sale->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        if ($balance->amount < $package?->getPriceClientPackage()?->getAmount()) {
            throw new MyCurrentException('COM001', 'Insufficient balance');
        }
        $lastSequence = $this->configureSequence->getLastSequence(CommunicationSalePackage::class);
        $transactionId = (new \DateTime('now'))->format('ymd').'02'.str_pad(
                $lastSequence,
                5,
                '0',
                STR_PAD_LEFT
            );
        $sale->setTransactionId($transactionId);
        $sale->setPackage($package);
        $sale->setTenant($user);
        $sale->setAmount($package?->getPriceClientPackage()?->getAmount());
        $sale->setCurrency($package?->getPriceClientPackage()?->getCurrency());
        $sale->getCalculatePrice();
        $sale->setState(CommunicationStateEnum::PENDING);

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
        $transactionId = $sale->getTransactionId();
        try {
            $user = $sale?->getTenant();
            if (!$user instanceof Account) {
                return;
            }
            $commercialOffice = $sale?->getCommercialOffice();
            if (is_null($commercialOffice)) {
                return;
            }
            $nationality = $sale?->getNationality();
            if (is_null($nationality)) {
                return;
            }
            $package = $sale?->getPackage();
            if (!$package instanceof CommunicationClientPackage) {
                return;
            }
            $urlSale = $user->getEnvironment()?->getBasePath().'/sale/package';

            $body = [
                'client' => [
                    'id' => $sale?->getIdentificationNumber(),
                    'name' => $sale?->getName(),
                    'identificationType' => $sale->getIdentificationType() ?? 1,
                    'arrivalDate' => $sale?->getArrivalAt() ? $sale->getArrivalAt()?->format('Y-m-d') : null,
                    'isAirport' => $commercialOffice->isIsAirport(),
                    'commercialOfficeId' => $commercialOffice?->getComId(),
                    'provinceId' => $commercialOffice?->getProvince()?->getComId(),
                    'nationality' => $nationality?->getComId(),
                ],
                'packageInfo' => [
                    'id' => $package?->getPriceClientPackage()?->getProduct()?->getPackageId(),
                    'packageType' => $package?->getPriceClientPackage()?->getProduct()?->getPackageType(),
                ],
                'transactionId' => $transactionId,
                'environment' => $user?->getEnvironment()?->getType(),
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
            $this->em->flush();
            $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()));
        } catch (\Exception $exc) {

        }
    }

    /**
     * @param int $saleId
     * @return \App\Entity\CommunicationSaleInfo|null
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
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
    public function checkStatusOrder(int $saleId, bool $isProcess = null): void
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        if (is_null($sale) || $sale->getState() !== CommunicationStateEnum::PENDING) {
            return;
        }
        $tenant = $sale->getTenant();
        $url = $tenant?->getEnvironment()?->getBasePath().'/sale/sale-info';

        $body = [
            'environment' => $tenant?->getEnvironment()?->getType(),
            'transactionId' => $sale->getTransactionId(),
        ];

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

        try {
            $response = $rechargeResponse->toArray();
            $responseInfo = (object)$response;
            $result = (object)$responseInfo->result;
            $sale->setTransactionStatus($response);
            if (property_exists($responseInfo, 'orderId') && isset($responseInfo->orderId) && $result->valueOk) {
                $orderId = $responseInfo->orderId;
                $sale->setTransactionOrder($orderId);
                if ($sale instanceof CommunicationSaleRecharge) {
                    $sale->setState(CommunicationStateEnum::COMPLETED);
                } elseif (isset($responseInfo->fullResponse['Sale']) && $sale instanceof CommunicationSalePackage) {
                    $sale->setState(CommunicationStateEnum::COMPLETED);
                } else {
                    return;
                }

                $balanceOperation = new BalanceOperation();
                $balanceOperation->setTenant($tenant);
                $balanceOperation->setAmount($sale->getTotalPrice());
                $balanceOperation->setCurrency($sale->getCurrency());
                $balanceOperation->setState('COMPLETED');
                $balanceOperation->setOperationType('DEBIT');
                $balanceOperation->getCalculateTotal();
                $balanceOperation->setTotalAmount($balanceOperation->getTotalAmount() * -1);
                $balanceOperation->setTotalCurrency($sale->getCurrency());
                $balanceOperation->setCommunicationSale($sale);
                $this->em->persist($balanceOperation);
                $this->historicalSaleService->createHistoricalCommunication(
                    $sale->getId(),
                    CommunicationStateEnum::COMPLETED,
                    $response
                );
            } elseif (!is_null($result) && isset($responseInfo->status) && $result->valueOk && $responseInfo->status === CommunicationStateEnum::REJECTED->value) {
                $sale->setState(CommunicationStateEnum::REJECTED);
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
                        $this->historicalSaleService->createHistoricalCommunication(
                            $sale->getId(),
                            CommunicationStateEnum::REJECTED,
                            $response
                        );
                    } elseif ((int)$result->code !== -1) {
                        $sale->setState(CommunicationStateEnum::PENDING);
                        $this->historicalSaleService->createHistoricalCommunication(
                            $sale->getId(),
                            CommunicationStateEnum::PENDING,
                            $response
                        );
                    }
                } else {
                    $sale->setState(CommunicationStateEnum::REJECTED);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::PENDING,
                        $response
                    );
                }
            } else {
                if (is_null($result)) {
                    $sale->setState(CommunicationStateEnum::PENDING);
                    $this->historicalSaleService->createHistoricalCommunication(
                        $sale->getId(),
                        CommunicationStateEnum::PENDING
                    );
                }
            }
            $this->em->flush();
        } catch (\Exception $e) {
            $infoResponse = $this->serializer->decode($rechargeResponse->getContent(false), 'json');
            $message = $e->getMessage();
            $this->logger->error($rechargeResponse->getContent(false), $infoResponse);
            if ($e instanceof ClientException && $e->getCode() === 404 && $isProcess) {
                $this->tryAgainWithTransaction($saleId);
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
                    CommunicationStateEnum::FAILED,
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
                    CommunicationStateEnum::FAILED,
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
        $sales = $this->em->getRepository(CommunicationSaleInfo::class)->getLastPending();
        foreach ($sales as $sale) {
            $this->checkStatusOrder($sale->getId(), true);
        }
    }
}
