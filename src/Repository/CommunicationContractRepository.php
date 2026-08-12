<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationContract>
 *
 * @method CommunicationContract|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationContract|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationContract[]    findAll()
 * @method CommunicationContract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationContract::class);
    }

    /**
     * Contratos propios vigentes de un tenant, con su CommunicationPackage
     * ya cargado (un solo JOIN — el match es por FK, no por tupla). Usado
     * por PackageCatalogResolver::catalogFor() (Fase 2): si esto no está
     * vacío, el catálogo visible del cliente es EXACTAMENTE estos paquetes.
     *
     * @return list<CommunicationContract>
     */
    public function findActiveForTenant(Account $tenant, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->baseActiveQueryBuilder($now)
            ->andWhere('c.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getResult();
    }

    /**
     * Contratos "por defecto" vigentes (tenant IS NULL) — segunda rama de
     * PackageCatalogResolver::catalogFor() cuando el cliente no tiene
     * contrato propio.
     *
     * @return list<CommunicationContract>
     */
    public function findActiveDefaults(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->baseActiveQueryBuilder($now)
            ->andWhere('c.tenant IS NULL')
            ->getQuery()
            ->getResult();
    }

    private function baseActiveQueryBuilder(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->innerJoin('c.communicationPackage', 'p')
            ->andWhere('c.startAt <= :now')
            ->andWhere('c.endAt IS NULL OR c.endAt > :now')
            ->setParameter('now', $now)
            ->orderBy('c.startAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');
    }
}
