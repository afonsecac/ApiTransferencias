<?php

namespace App\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Repository\BalanceOperationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        '112' => 'Los datos para la modificaciÃ³n de la venta no son vÃ¡lidos',
        '151' => 'El nÃºmero de telÃ©fono no existe',
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
    ];

    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        private readonly HttpClientInterface $httpClient,
        private readonly BalanceOperationRepository $balanceRepository,
        private readonly ConfigureSequenceService $configureSequence,
    ) {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo
        );
    }

    /**
     * @param CommunicationSaleRecharge $recharge
     * @return CommunicationSaleRecharge|null
     * @throws MyCurrentException
     * @throws TransportExceptionInterface
     * @throws \JsonException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function processRecharge(CommunicationSaleRecharge $recharge): CommunicationSaleRecharge|null
    {
        $user = $this->security->getUser();
        $orderId = null;
        $bodyCheck = null;
        $comInfo = [];
        $failedByDuplicated = false;
        if (is_null($user) || !$user instanceof Account) {
            throw new AccessDeniedException();
        }
        $balance = $this->balanceRepository->getBalanceOutput($user->getId());
        $package = $this->em->getRepository(CommunicationPackage::class)->getPackageById(
            $recharge->getPackageId(),
            $user
        );
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        if ($balance < $package->getAmount()) {
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
            $this->em->flush();

            $urlRecharge = $user->getEnvironment()?->getBasePath().'/sale/recharge';

            $body = [
                'phoneNumber' => $recharge->getPhoneNumber(),
                'productCode' => $package->getComId(),
                'productPrice' => round($package->getComPrice(), 2),
                'transactionId' => $transactionId,
                'environment' => $user->getEnvironment()?->getType(),
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


            $rechargeInfo = $this->serializer->decode($rechargeResponse->getContent(), 'json');
            $rechargeResult = null;
            if (is_array($rechargeInfo)) {
                $rechargeResult = (object)((object)$rechargeInfo)->result;
            } else {
                $rechargeResult = (object)$rechargeInfo->result;
            }
            $txStatus = (boolean)$rechargeResult->valueOk;
            if ($txStatus) {
                $orderId = $rechargeResult->orderId;

                $bodyCheck = [
                    'orderId' => $orderId,
                    'transactionId' => $transactionId,
                    'environment' => $user->getEnvironment()?->getType(),
                ];
                $recharge->setState(CommunicationStateEnum::COMPLETED);
                $comInfo = [
                    'info' => $rechargeInfo,
                ];

                $balanceOperation = new BalanceOperation();
                $balanceOperation->setTenant($user);
                $balanceOperation->setAmount($package->getAmount());
                $balanceOperation->setCurrency($package->getCurrency());
                $balanceOperation->setState('COMPLETED');
                $balanceOperation->setOperationType('DEBIT');
                $balanceOperation->getCalculateTotal();
                $balanceOperation->setTotalAmount($balanceOperation->getTotalAmount() * -1);
                $balanceOperation->setTotalCurrency($package->getCurrency());
                $balanceOperation->setCommunicationSale($recharge);
                $this->em->persist($balanceOperation);
            } else {
                $code = $rechargeResult->code;
                $recharge->setState(CommunicationStateEnum::REJECTED);
                $errMsg = self::ETECSA_INFO_ERROR[$code];

                if ($errMsg) {
                    $errMsg = utf8_decode($errMsg);
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
                        'transactionID' => $transactionId,
                        'body' => $body,
                        'bodyCheck' => $bodyCheck,
                    ],
                ];
            }
            $recharge->setTransactionStatus($comInfo);
            $this->em->flush();
        } catch (ClientExceptionInterface|TimeoutException $exc) {
            $recharge->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'The provider server no response'
                    ),
                    'orderID' => $orderId,
                    'code' => 'COM004',
                    'transactionID' => $transactionId,
                    'body' => $body,
                    'bodyCheck' => $bodyCheck,
                ],
            ];
            $recharge->setTransactionStatus($comInfo);
        } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $exc) {
            $recharge->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'Unexpected error'
                    ),
                    'code' => 'COM000',
                    'transactionID' => $transactionId,
                ],
            ];
            $recharge->setTransactionStatus($comInfo);
        } catch (\Exception $ex) {
            $recharge->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'Unexpected error'
                    ),
                    'code' => 'COM000',
                    'transactionID' => $transactionId,
                ],
            ];
            $recharge->setTransactionStatus($comInfo);
            if ($ex instanceof Exception\UniqueConstraintViolationException) {
                $exc = $ex->getPrevious();
                if (strpos($exc->getMessage(), "unique_identification_client") >= 0) {
                    $comInfo = [
                        'error' => [
                            'code' => 'COM005',
                            'message' => sprintf(
                                "action=Recharge, Message=%s",
                                'Duplicate transaction by customer'
                            ),
                            'transactionID' => $transactionId,
                        ],
                    ];
                    $recharge->setTransactionStatus($comInfo);
                }
            }
        }

        return $recharge;
    }

    /**
     * @throws \App\Exception\MyCurrentException
     */
    public function executeSale(CommunicationSalePackage $sale): CommunicationSalePackage|null
    {
        $user = $this->security->getUser();
        $orderId = null;
        $bodyCheck = null;
        $body = null;
        $comInfo = [];
        if (is_null($user) || !$user instanceof Account) {
            throw new AccessDeniedException();
        }
        $balance = $this->balanceRepository->getBalanceOutput($user->getId());
        $package = $this->em->getRepository(CommunicationPackage::class)->getPackageById($sale->getPackageId(), $user);
        if (is_null($package)) {
            throw new MyCurrentException('COM003', 'The package don\'t exist');
        }
        if ($balance < $package->getAmount()) {
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
        $sale->setAmount($package->getAmount());
        $sale->setCurrency($package->getCurrency());
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
            $this->em->flush();

            $urlSale = $user->getEnvironment()?->getBasePath().'/sale/package';

            $body = [
                'client' => [
                    'id' => $sale->getIdentificationNumber(),
                    'name' => $sale->getName(),
                    'identificationType' => 9,
                    'arrivalDate' => $sale->getArrivalAt(),
                    'isAirport' => $commercialOffice->isIsAirport(),
                    'commercialOfficeId' => $commercialOffice?->getComId(),
                    'provinceId' => $commercialOffice?->getProvince()?->getComId(),
                    'nationality' => $nationality?->getComId(),
                ],
                'packageInfo' => [
                    'id' => $package?->getComId(),
                    'packageType' => $package?->getComPackageType(),
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

            $saleInfo = $this->serializer->decode($saleResponse->getContent(), 'json');
            if (is_array($saleInfo)) {
                $saleResult = (object)((object)$saleInfo)->result;
                $saleData = ((object)$saleInfo)->sale;
            } else {
                $saleResult = (object)$saleInfo->result;
                $saleData = $saleInfo->sale;
            }

            $txStatus = (boolean) $saleResult->valueOk;
            if ($txStatus) {
                $sale->setState(CommunicationStateEnum::COMPLETED);
                $comInfo = [
                    'sale' => $saleData
                ];
                $balanceOperation = new BalanceOperation();
                $balanceOperation->setTenant($user);
                $balanceOperation->setAmount($package->getAmount());
                $balanceOperation->setCurrency($package->getCurrency());
                $balanceOperation->setState('COMPLETED');
                $balanceOperation->setOperationType('DEBIT');
                $balanceOperation->getCalculateTotal();
                $balanceOperation->setTotalAmount($balanceOperation->getTotalAmount() * -1);
                $balanceOperation->setTotalCurrency($package->getCurrency());
                $balanceOperation->setCommunicationSale($sale);
                $this->em->persist($balanceOperation);
            } else {
                $code = $saleResult->code;
                $sale->setState(CommunicationStateEnum::REJECTED);
                $errMsg = self::ETECSA_INFO_ERROR[$code];

                if ($errMsg) {
                    $errMsg = utf8_decode($errMsg);
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
                        'transactionID' => $transactionId,
                        'body' => $body,
                        'bodyCheck' => $bodyCheck,
                    ],
                ];
            }
            $sale->setTransactionStatus($comInfo);
            $this->em->flush();
        } catch (ClientExceptionInterface|TimeoutException $exc) {
            $sale->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'The provider server no response'
                    ),
                    'orderID' => $orderId,
                    'code' => 'COM004',
                    'transactionID' => $transactionId,
                ],
            ];
            $sale->setTransactionStatus($comInfo);
        } catch (RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $exc) {
            $sale->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'Unexpected error'
                    ),
                    'code' => 'COM000',
                    'transactionID' => $transactionId,
                ],
            ];
            $sale->setTransactionStatus($comInfo);
        } catch (\Exception $exc) {
            $sale->setState(CommunicationStateEnum::REJECTED);
            $comInfo = [
                'error' => [
                    'message' => sprintf(
                        "action=Recharge, Message=%s",
                        'Unexpected error'
                    ),
                    'code' => 'COM000',
                    'transactionID' => $transactionId,
                ],
            ];
            $sale->setTransactionStatus($comInfo);
            if ($exc instanceof Exception\UniqueConstraintViolationException) {
                $ex = $exc->getPrevious();
                if (strpos($ex->getMessage(), "unique_identification_client") >= 0) {
                    $comInfo = [
                        'error' => [
                            'code' => 'COM005',
                            'message' => sprintf(
                                "action=Recharge, Message=%s",
                                'Duplicate transaction by customer'
                            ),
                            'transactionID' => $transactionId,
                        ],
                    ];
                    $sale->setTransactionStatus($comInfo);
                }
            }
        }

        return $sale;
    }
}
