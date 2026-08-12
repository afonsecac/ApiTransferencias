<?php

namespace App\Repository;

use App\Entity\ClientProviderRouting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<ClientProviderRouting>
 *
 * @method ClientProviderRouting|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientProviderRouting|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientProviderRouting[]    findAll()
 * @method ClientProviderRouting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientProviderRoutingRepository extends ServiceEntityRepository
{
    private const CACHE_TAG = 'provider_routing';

    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(service: 'cache.provider_routing')]
        private readonly TagAwareCacheInterface $cache,
    ) {
        parent::__construct($registry, ClientProviderRouting::class);
    }

    /**
     * Resuelve, con caché, la fila de routing más específica para
     * (client, environment, saleType), de más a menos específico:
     *   1. (client, environment, saleType) exactos
     *   2. (client, environment, saleType IS NULL)
     *   3. (client, environment IS NULL, saleType)
     *   4. (client, environment IS NULL, saleType IS NULL)
     * Solo considera filas activas.
     */
    public function findResolvedFor(int $clientId, ?int $environmentId, ?string $saleType): ?ClientProviderRouting
    {
        $key = sprintf('cpr_%d_%s_%s', $clientId, $environmentId ?? 'null', $saleType ?? 'null');

        return $this->cache->get($key, function (ItemInterface $item) use ($clientId, $environmentId, $saleType) {
            $item->tag([self::CACHE_TAG]);

            $qb = $this->createQueryBuilder('cpr')
                ->andWhere('cpr.client = :clientId')
                ->andWhere('cpr.isActive = :active')
                ->setParameter('clientId', $clientId)
                ->setParameter('active', true);

            if ($environmentId !== null) {
                $qb->andWhere($qb->expr()->orX(
                    'cpr.environment IS NULL',
                    'cpr.environment = :environmentId',
                ))->setParameter('environmentId', $environmentId);
            } else {
                $qb->andWhere('cpr.environment IS NULL');
            }

            if ($saleType !== null) {
                $qb->andWhere($qb->expr()->orX(
                    'cpr.saleType IS NULL',
                    'cpr.saleType = :saleType',
                ))->setParameter('saleType', $saleType);
            } else {
                $qb->andWhere('cpr.saleType IS NULL');
            }

            $qb->addSelect('(CASE WHEN cpr.environment IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN envSpecificity')
               ->addSelect('(CASE WHEN cpr.saleType IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN saleTypeSpecificity')
               ->orderBy('envSpecificity', 'DESC')
               ->addOrderBy('saleTypeSpecificity', 'DESC')
               ->setMaxResults(1);

            return $qb->getQuery()->getOneOrNullResult();
        });
    }

    /**
     * @return list<string> códigos de proveedor únicos activos para el cliente,
     *   en cualquier scope (environment/saleType), para allowedForClient().
     */
    public function findActiveProviderCodesForClient(int $clientId): array
    {
        $key = sprintf('cpr_allowed_%d', $clientId);

        return $this->cache->get($key, function (ItemInterface $item) use ($clientId) {
            $item->tag([self::CACHE_TAG]);

            $rows = $this->createQueryBuilder('cpr')
                ->select('DISTINCT cpr.provider')
                ->andWhere('cpr.client = :clientId')
                ->andWhere('cpr.isActive = :active')
                ->setParameter('clientId', $clientId)
                ->setParameter('active', true)
                ->getQuery()
                ->getScalarResult();

            return array_column($rows, 'provider');
        });
    }

    /**
     * Lista PLANA (sin distinguir environment/saleType) de las filas activas
     * de un cliente, en orden de prioridad — lo que consultará
     * ProviderDispatchResolver (V2 Fase 2) para probar proveedores en orden
     * hasta que uno acepte la venta. Desempate por id ASC cuando dos filas
     * comparten priority (p. ej. ambas en el valor DEFAULT tras el backfill
     * de Version20260810120200), para que el orden sea determinista.
     *
     * @return list<ClientProviderRouting>
     */
    public function findActiveProvidersOrderedForClient(int $clientId): array
    {
        $key = sprintf('cpr_priority_%d', $clientId);

        return $this->cache->get($key, function (ItemInterface $item) use ($clientId) {
            $item->tag([self::CACHE_TAG]);

            return $this->createQueryBuilder('cpr')
                ->andWhere('cpr.client = :clientId')
                ->andWhere('cpr.isActive = :active')
                ->setParameter('clientId', $clientId)
                ->setParameter('active', true)
                ->orderBy('cpr.priority', 'ASC')
                ->addOrderBy('cpr.id', 'ASC')
                ->getQuery()
                ->getResult();
        });
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }
}
