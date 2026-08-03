<?php

namespace App\Repository;

use App\Entity\CurrencyExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurrencyExchangeRate>
 *
 * @method CurrencyExchangeRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method CurrencyExchangeRate|null findOneBy(array $criteria, array $orderBy = null)
 * @method CurrencyExchangeRate[]    findAll()
 * @method CurrencyExchangeRate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CurrencyExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurrencyExchangeRate::class);
    }

    /**
     * Fila más reciente para (base, target) — nunca hace una llamada de red,
     * solo lee lo que ya sincronizó app:provider:sync-exchange-rates.
     */
    public function findLatest(string $baseCurrency, string $targetCurrency): ?CurrencyExchangeRate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.baseCurrency = :base')
            ->andWhere('r.targetCurrency = :target')
            ->setParameter('base', $baseCurrency)
            ->setParameter('target', $targetCurrency)
            ->orderBy('r.rateDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Histórico paginado, más reciente primero. Para que un admin pueda ver
     * qué tasas se han guardado (y auditar cuál se usó en una conversión
     * pasada) — ver DashboardProviderRoutingController::exchangeRatesHistory().
     *
     * @return CurrencyExchangeRate[]
     */
    public function findHistory(?string $targetCurrency, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.rateDate', 'DESC')
            ->addOrderBy('r.targetCurrency', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($targetCurrency !== null) {
            $qb->andWhere('r.targetCurrency = :target')->setParameter('target', $targetCurrency);
        }

        return $qb->getQuery()->getResult();
    }

    public function countHistory(?string $targetCurrency): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');

        if ($targetCurrency !== null) {
            $qb->andWhere('r.targetCurrency = :target')->setParameter('target', $targetCurrency);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
