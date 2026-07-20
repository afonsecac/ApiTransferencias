<?php

namespace App\Service;

use App\DTO\CreateClientDto;
use App\DTO\UpdateClientDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\Environment;
use App\EntityPaginator\PaginatorResponse;
use App\Exception\MyCurrentException;
use App\Message\ClientCreatedMessage;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommonService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ClientService extends CommonService
{
    public function __construct(
        EntityManagerInterface      $em,
        Security                    $security,
        ParameterBagInterface       $parameters,
        MailerInterface             $mailer,
        LoggerInterface             $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository       $environmentRepository,
        SysConfigRepository         $sysConfigRepo,
        SerializerInterface         $serializer,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }

    /**
     * @param array $params
     * @return \App\EntityPaginator\PaginatorResponse
     */
    public function getAllClients(array $params): PaginatorResponse
    {
        $orderBy = [];
        if (isset($params['orderBy'])) {
            $orderBy = [
                'orderBy' => $params['orderBy'],
                'direction' => $params['direction']
            ];
        }
        /** @var \App\Repository\ClientRepository $clientRepo */
        $clientRepo = $this->em->getRepository(Client::class);
        return $clientRepo->getAllClients($params, $orderBy);
    }

    public function create(CreateClientDto $dto, int $environmentId): Client
    {
        $environment = $this->em->getRepository(Environment::class)->find($environmentId);
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        $client = new Client();
        $client->setCompanyName($dto->getCompanyName());
        $client->setCompanyCountry($dto->getCompanyCountry());
        $client->setCompanyEmail($dto->getCompanyEmail());
        $client->setCompanyPhoneNumber($dto->getCompanyPhoneNumber());
        $client->setCompanyIdentification($dto->getCompanyIdentification());
        $client->setCompanyIdentificationType($dto->getCompanyIdentificationType());
        $client->setDiscountOfClient($dto->getDiscountOfClient());

        if ($dto->getCompanyAddress() !== null)    $client->setCompanyAddress($dto->getCompanyAddress());
        if ($dto->getCompanyZipCode() !== null)    $client->setCompanyZipCode($dto->getCompanyZipCode());
        if ($dto->getMinBalance() !== null)        $client->setMinBalance($dto->getMinBalance());
        if ($dto->getCriticalBalance() !== null)   $client->setCriticalBalance($dto->getCriticalBalance());
        if ($dto->getCurrency() !== null)          $client->setCurrency($dto->getCurrency());
        if ($dto->getIsAlert() !== null)           $client->setAlert($dto->getIsAlert());
        if ($dto->getContractWith() !== null)      $client->setContractWith($dto->getContractWith());
        if ($dto->getIsActive() !== null)          $client->setActive($dto->getIsActive());

        $account = new Account();
        $account->setEnvironment($environment);
        $account->setClient($client);
        $account->setIsActive(false);
        $account->setOrigin('*');
        $account->setEnvironmentName($environment->getType() ?? '');
        if ($dto->getContractCurrency() !== null) {
            $account->setContractCurrency($dto->getContractCurrency());
        }

        try {
            $this->em->persist($client);
            $this->em->persist($account);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new MyCurrentException(
                'CLIENT_DUPLICATE_IDENTIFICATION',
                'A client with that country, identification and type already exists.',
                409
            );
        }

        $this->messageBus->dispatch(new ClientCreatedMessage(
            email:           $client->getCompanyEmail(),
            companyName:     $client->getCompanyName(),
            accessToken:     (string) $account->getAccessToken(),
            environmentType: $account->getEnvironmentName(),
            contractWith:    $client->getContractWith(),
            origin:          $account->getOrigin(),
        ));

        return $client;
    }

    public function update(Client $client, UpdateClientDto $dto): Client
    {
        if ($dto->getCompanyName() !== null)               $client->setCompanyName($dto->getCompanyName());
        if ($dto->getCompanyAddress() !== null)            $client->setCompanyAddress($dto->getCompanyAddress());
        if ($dto->getCompanyCountry() !== null)            $client->setCompanyCountry($dto->getCompanyCountry());
        if ($dto->getCompanyZipCode() !== null)            $client->setCompanyZipCode($dto->getCompanyZipCode());
        if ($dto->getCompanyEmail() !== null)              $client->setCompanyEmail($dto->getCompanyEmail());
        if ($dto->getCompanyPhoneNumber() !== null)        $client->setCompanyPhoneNumber($dto->getCompanyPhoneNumber());
        if ($dto->getDiscountOfClient() !== null)          $client->setDiscountOfClient($dto->getDiscountOfClient());
        if ($dto->getCompanyIdentification() !== null)     $client->setCompanyIdentification($dto->getCompanyIdentification());
        if ($dto->getCompanyIdentificationType() !== null) $client->setCompanyIdentificationType($dto->getCompanyIdentificationType());
        if ($dto->getMinBalance() !== null)                $client->setMinBalance($dto->getMinBalance());
        if ($dto->getCriticalBalance() !== null)           $client->setCriticalBalance($dto->getCriticalBalance());
        if ($dto->getCurrency() !== null)                  $client->setCurrency($dto->getCurrency());
        if ($dto->getIsAlert() !== null)                   $client->setAlert($dto->getIsAlert());
        if ($dto->getContractWith() !== null)              $client->setContractWith($dto->getContractWith());
        if ($dto->getIsActive() !== null)                  $client->setActive($dto->getIsActive());

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new MyCurrentException(
                'CLIENT_DUPLICATE_IDENTIFICATION',
                'A client with that country, identification and type already exists.',
                409
            );
        }

        return $client;
    }
}
