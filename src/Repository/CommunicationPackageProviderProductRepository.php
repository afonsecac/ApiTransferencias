<?php

namespace App\Repository;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPackageProviderProduct>
 *
 * @method CommunicationPackageProviderProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPackageProviderProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPackageProviderProduct[]    findAll()
 * @method CommunicationPackageProviderProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPackageProviderProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPackageProviderProduct::class);
    }

    public function findForPackageAndProvider(CommunicationPackage $package, string $provider): ?CommunicationPackageProviderProduct
    {
        return $this->findOneBy([
            'communicationPackage' => $package,
            'provider' => $provider,
        ]);
    }

    /**
     * @return list<CommunicationPackageProviderProduct>
     */
    public function findAllForPackage(CommunicationPackage $package): array
    {
        return $this->findBy(['communicationPackage' => $package]);
    }

    /**
     * Ids de $packageIds que tienen al menos un vínculo — una sola query
     * para colorear el ícono de cobertura en el listado sin caer en N+1
     * (una consulta de bindings por fila).
     *
     * @param list<int> $packageIds
     * @return list<int>
     */
    public function findPackageIdsWithBindings(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.communicationPackage) AS packageId')
            ->where('b.communicationPackage IN (:ids)')
            ->setParameter('ids', $packageIds)
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row) => (int) $row['packageId'], $rows);
    }
}
