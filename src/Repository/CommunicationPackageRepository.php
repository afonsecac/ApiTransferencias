<?php

namespace App\Repository;

use App\Entity\CommunicationPackage;
use App\Service\Pricing\DestinationKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPackage>
 *
 * @method CommunicationPackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPackage[]    findAll()
 * @method CommunicationPackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPackage::class);
    }

    /**
     * Catálogo completo activo y dentro de ventana de vigencia, ordenado
     * para presentación — la rama "sin contrato ni default" de
     * PackageCatalogResolver::catalogFor() (Fase 2) parte de aquí.
     *
     * @return list<CommunicationPackage>
     */
    public function findActiveCatalog(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.activeStartAt <= :now')
            ->andWhere('p.activeEndAt IS NULL OR p.activeEndAt > :now')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paquete cuyo destino coincide con la tupla dada, con tolerancia de
     * coma flotante (DestinationKey::EPSILON) — usado por
     * CommunicationContractService::createByRange() para resolver, monto a
     * monto, a qué CommunicationPackage corresponde cada paso del rango.
     */
    public function findByDestination(float $amount, string $currency): ?CommunicationPackage
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.destinationAmount BETWEEN :min AND :max')
            ->andWhere('p.destinationCurrency = :currency')
            ->setParameter('min', $amount - DestinationKey::EPSILON)
            ->setParameter('max', $amount + DestinationKey::EPSILON)
            ->setParameter('currency', strtoupper($currency))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
