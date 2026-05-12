<?php

namespace App\Service;

use App\DTO\UpdateClientDto;
use App\Entity\Client;
use App\EntityPaginator\PaginatorResponse;
use App\Exception\MyCurrentException;
use App\Service\CommonService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ClientService extends CommonService
{
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
