<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Persistence;

use App\Entity\Client;
use App\Market\Organizacion\Domain\Buyer;
use App\Market\Organizacion\Domain\BuyerRegistry;
use App\Market\Organizacion\Domain\OrgUnit;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Los compradores son los `Client` de comunicaciones: este adaptador es el único sitio del contexto que los conoce.
 */
final class DoctrineBuyerRegistry implements BuyerRegistry
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $clientId): ?Buyer
    {
        $client = $this->em->find(Client::class, $clientId);
        if ($client === null) {
            return null;
        }

        return new Buyer(
            $clientId,
            (string) $client->getCompanyName(),
            (string) $client->getCompanyCountry(),
            (string) $client->getCompanyIdentification(),
            $client->getCompanyAddress(),
            (string) $client->getCompanyEmail(),
        );
    }

    public function orgUnitIdOf(int $clientId): ?int
    {
        return $this->em->find(Client::class, $clientId)?->getOrgUnit()?->id();
    }

    public function link(int $clientId, OrgUnit $unit): void
    {
        $client = $this->em->find(Client::class, $clientId);
        $client?->setOrgUnit($unit);
        $this->em->flush();
    }
}
