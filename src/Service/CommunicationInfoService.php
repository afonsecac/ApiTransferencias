<?php

namespace App\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\DTO\RequestInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\User;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CommunicationInfoService extends CommonService
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
        private readonly EtecsaGatewayClient $etecsaClient,
        private readonly HistoricalSaleService $historicalSaleService,
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

    public function querySale(RequestInfo $requestInfo)
    {
        $user = $this->security->getUser();
        if (is_null($user)) {
            throw new AccessDeniedException("The user is not allowed to access this resource.");
        }
        if (!$user instanceof User || !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException("Access Denied exception");
        }
        $operationType = strtoupper($requestInfo->getType());
        $class = CommunicationSaleRecharge::class;
        if ($operationType === 'SA') {
            $class = CommunicationSalePackage::class;
        }
        if ($requestInfo->getInternalTxId() !== null) {
            $params = [
                'transactionId' => $requestInfo->getInternalTxId(),
            ];
        } else {
            $params = [
                'clientTransactionId' => $requestInfo->getClientTxId(),
            ];
        }

        // Guardia: no consultar a la gateway si la venta no fue enviada previamente
        $operationSale = $this->em->getRepository($class)->findOneBy($params);
        $date = new \DateTimeImmutable('now');
        if (is_null($operationSale)) {
            throw new EntityNotFoundException("Operation not found");
        }
        if ($date->diff($operationSale->getCreatedAt())->days > 7) {
            throw new \Exception("The operation is older than 7 days and not available to query");
        }

        $tenant = $operationSale->getTenant();
        if (is_null($tenant)) {
            return;
        }

        $env = $tenant->getEnvironment();

        $result = $this->etecsaClient->getSaleInfo($env, $operationSale->getTransactionId());

        $state = $operationSale->getState();
        if ($state !== null) {
            $this->historicalSaleService->createHistoricalCommunication(
                $operationSale->getId(),
                $state,
                $result
            );
            $this->em->flush();
        }

        return $result;
    }
}
