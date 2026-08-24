<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationClientPackage>
 *
 * @method CommunicationClientPackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationClientPackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationClientPackage[]    findAll()
 * @method CommunicationClientPackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationClientPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationClientPackage::class);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getPackageById(int $packageId, Account $account): CommunicationClientPackage|null
    {
        $currentDate = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 'a')
            ->addSelect('a')
            ->where('p.id = :id')
            ->andWhere('a.id = :aId')
            ->andWhere('p.activeStartAt <= :currentDate AND p.activeEndAt > :currentDate')
            ->setParameters(new ArrayCollection([
                new Parameter('id', $packageId),
                new Parameter('aId', $account->getId()),
                new Parameter('currentDate', $currentDate),
            ]))
            ->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    /**
     * Like getPackageById but also matches packages whose activeStartAt is in the future.
     * Used for reserve flow where the package belongs to a promotion that hasn't started yet.
     */
    public function getPackageByIdForReserve(int $packageId, Account $account): CommunicationClientPackage|null
    {
        $currentDate = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 'a')
            ->addSelect('a')
            ->where('p.id = :id')
            ->andWhere('a.id = :aId')
            ->andWhere('p.activeEndAt > :currentDate')
            ->setParameters(new ArrayCollection([
                new Parameter('id', $packageId),
                new Parameter('aId', $account->getId()),
                new Parameter('currentDate', $currentDate),
            ]))
            ->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    /**
     * @param string $env
     * @param int|null $tenant
     * @return CommunicationClientPackage[]
     */
    public function getAllPackages(string $env = 'TEST', ?int $tenant = null): array
    {
        $currentDate = new \DateTimeImmutable('now');
        // Antes del rediseño de precios, el environment se resolvía vía
        // priceClientPackage->product->environment — pero priceClientPackage
        // ahora es nullable (paquetes sin contrato), así que un join por ahí
        // dejaría fuera exactamente a los paquetes que este rediseño
        // introduce. Se usa el environment propio de la fila (ya lo
        // rellenan ClientCatalogImportService/PackagePriceService).
        $dql = $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 't')
            ->leftJoin('t.client', 'c')
            ->leftJoin('p.environment', 'e')
            ->select('p')
            ->addSelect('t')
            ->addSelect('c')
            ->where('p.activeStartAt <= :currentDate AND p.activeEndAt > :currentDate')
            ->andWhere('e.type = :type')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('type', $env);

        if (!is_null($tenant) && $tenant) {
            $dql->andWhere('t.id = :tenant')
                ->andWhere('t.isActive = :isActive')
                ->setParameter('tenant', $tenant)
                ->setParameter('isActive', true);
        }

        return $dql->orderBy('c.companyName')->addOrderBy('p.amount')->getQuery()->getResult();
    }
}
