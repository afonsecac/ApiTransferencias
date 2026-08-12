<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationPricePackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPricePackage>
 *
 * @method CommunicationPricePackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPricePackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPricePackage[]    findAll()
 * @method CommunicationPricePackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPricePackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPricePackage::class);
    }

    /**
     * @param int $productId
     * @param int|null $clientId
     * @return array
     */
    public function getIdsWithPrices(int $productId, ?int $clientId = null): array
    {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('p')
            ->leftJoin('p.product', 'pd')
            ->leftJoin('p.priceUsed', 'u')
            ->select('u.id')
            ->where('pd.id = :pdId')
            ->andWhere('pd.enabled = :enabled')
            ->andWhere('p.isActive = :enabled')
            ->andWhere('u.isActive = :enabled')
            ->andWhere('u.validStartAt <= :currentDate AND (u.validEndAt > :currentDate OR u.validEndAt IS NULL)')
            ->andWhere('pd.initialDate <= :currentDate AND (pd.endDateAt > :currentDate OR pd.endDateAt IS NULL)')
            ->andWhere('p.activeStartAt <= :currentDate AND (p.activeEndAt > :currentDate OR p.activeEndAt IS NULL)')
            ->setParameters(new ArrayCollection([
                new Parameter('pdId', $productId),
                new Parameter('enabled', true),
                new Parameter('currentDate', $currentDate),
            ]));
        if (!is_null($clientId)) {
            $dql = $dql->leftJoin('p.tenant', 't')
                ->andWhere('t.id = :client')
                ->setParameter('client', $clientId);
        } else {
            $dql = $dql->andWhere('p.tenant IS NULL');
        }

        return $dql->orderBy('p.price', 'ASC')
            ->getQuery()->getScalarResult();
    }

    public function getPricesByEnvironment(string $env = 'TEST', ?int $tenantId = null): array
    {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('pp')
            ->leftJoin('pp.product', 'p')
            ->leftJoin('p.environment', 'e')
            ->where('e.type = :type')
            ->andWhere('pp.activeStartAt <= :currentDate')
            ->andWhere('pp.activeEndAt > :currentDate')
            ->andWhere('p.initialDate <= :currentDate')
            ->andWhere('p.endDateAt > :currentDate')
            ->andWhere('p.enabled <= :enabled')
            ->setParameters(new ArrayCollection([
                new Parameter('type', $env),
                new Parameter('currentDate', $currentDate),
                new Parameter('enabled', true),
            ]));
        if (!is_null($tenantId)) {
            $dql = $dql
                ->leftJoin('pp.tenant', 't')->andWhere('t.id = :tenant')
                ->setParameter('tenant', $tenantId);
        } else {
            $dql->andWhere('pp.tenant IS NULL');
        }

        return $dql->orderBy('pp.price', 'ASC')->getQuery()->getResult();
    }

    /**
     * El contrato vigente de un tenant para un paquete referencia concreto
     * (ver CommunicationClientPackage::resolveContractKey()) — usado por
     * PackageSalePriceResolver cuando el paquete no trae ya un contrato
     * congelado (priceClientPackage). El más reciente gana si hay más de
     * uno vigente (no debería ocurrir por la idempotencia de
     * PackageContractService, pero no se asume).
     */
    public function findActiveContract(Account $tenant, int $referencePackageId, \DateTimeImmutable $now): ?CommunicationPricePackage
    {
        return $this->baseActiveContractQueryBuilder($now)
            ->andWhere('pp.tenant = :tenant')
            ->andWhere('pp.referencePackage = :referencePackageId')
            ->setParameter('tenant', $tenant)
            ->setParameter('referencePackageId', $referencePackageId)
            ->orderBy('pp.activeStartAt', 'DESC')
            ->addOrderBy('pp.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Variante en lote de findActiveContract(), para
     * PackageSalePriceResolver::resolveMany() — una sola query para
     * resolver un listado paginado entero en vez de N+1.
     *
     * @param int[] $referencePackageIds
     * @return CommunicationPricePackage[] indexados por referencePackage id
     */
    public function findActiveContractsFor(Account $tenant, array $referencePackageIds): array
    {
        if ($referencePackageIds === []) {
            return [];
        }

        $now = new \DateTimeImmutable();
        $rows = $this->baseActiveContractQueryBuilder($now)
            ->andWhere('pp.tenant = :tenant')
            ->andWhere('pp.referencePackage IN (:referencePackageIds)')
            ->setParameter('tenant', $tenant)
            ->setParameter('referencePackageIds', $referencePackageIds)
            ->orderBy('pp.activeStartAt', 'DESC')
            ->addOrderBy('pp.id', 'DESC')
            ->getQuery()->getResult();

        $byReference = [];
        foreach ($rows as $row) {
            $key = $row->getReferencePackage()?->getId();
            // La primera fila por referencePackage es la más reciente
            // (orden ya aplicado arriba) — coincide con el criterio de
            // findActiveContract().
            if ($key !== null && !isset($byReference[$key])) {
                $byReference[$key] = $row;
            }
        }

        return $byReference;
    }

    private function baseActiveContractQueryBuilder(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('pp')
            ->where('pp.isContract = :isContract')
            ->andWhere('pp.isActive = :isActive')
            ->andWhere('pp.activeStartAt <= :now')
            ->andWhere('pp.activeEndAt IS NULL OR pp.activeEndAt > :now')
            ->setParameter('isContract', true)
            ->setParameter('isActive', true)
            ->setParameter('now', $now);
    }
}
