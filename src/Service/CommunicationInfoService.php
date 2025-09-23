<?php

namespace App\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\DTO\Out\InfoResult;
use App\DTO\RequestInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\User;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CommunicationInfoService extends CommonService
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
     * @param \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient
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
     * @param \App\DTO\RequestInfo $requestInfo
     * @return mixed|object|string|void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
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
        $url = $tenant->getEnvironment()?->getBasePath().'/information/status';

        $body = [
            'environment' => $tenant?->getEnvironment()?->getType(),
            'transactionId' => $operationSale->getTransactionId(),
        ];

        try {
            $queryResponse = $this->httpClient->request(
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

            $serviceResult = $queryResponse->getContent();

            return $this->serializer->deserialize(
                $serviceResult,
                InfoResult::class,
                'json',
            );
        } catch (ClientExceptionInterface| TransportExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
            throw $e;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}