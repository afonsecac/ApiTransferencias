<?php

namespace App\Repository;

use App\Entity\ProviderAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderAvailability>
 *
 * @method ProviderAvailability|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProviderAvailability|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProviderAvailability[]    findAll()
 * @method ProviderAvailability[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProviderAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderAvailability::class);
    }

    /**
     * Sin caché deliberadamente: se consulta en el camino caliente de cada
     * venta (App\Service\Provider\ProviderAvailabilityService::canDispatchTo())
     * y cache.app es de sistema de ficheros por proceso — un flag cacheado
     * quedaría obsoleto justo en el worker que despacha. La tabla tiene a lo
     * sumo unas pocas filas (proveedores x entornos) con índice único.
     */
    public function findOneByProviderAndType(string $provider, string $environmentType): ?ProviderAvailability
    {
        return $this->findOneBy(['provider' => $provider, 'environmentType' => $environmentType]);
    }

    /**
     * @return array<string, ProviderAvailability> indexado por "PROVIDER|TYPE"
     */
    public function findAllIndexed(): array
    {
        $indexed = [];
        foreach ($this->findAll() as $row) {
            $indexed[$row->getProvider() . '|' . $row->getEnvironmentType()] = $row;
        }

        return $indexed;
    }
}
