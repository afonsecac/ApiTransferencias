<?php

namespace App\Repository;


use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\EntityPaginator\PaginatorResponse;
use App\Service\Pricing\DestinationKey;
use App\Util\IPaginationResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationProduct>
 *
 * @method CommunicationProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationProduct[]    findAll()
 * @method CommunicationProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationProduct::class);
    }

    /**
     * Campos por los que el dashboard puede ordenar el listado de productos
     * (alias de la consulta: p = producto, e = entorno). Lista blanca: el
     * valor de orderBy llega del cliente.
     */
    private const SORTABLE_FIELDS = [
        'id' => 'p.id',
        'packageId' => 'p.packageId',
        'description' => 'p.description',
        'packageType' => 'p.packageType',
        'productType' => 'p.productType',
        'price' => 'p.price',
        'enabled' => 'p.enabled',
        'initialDate' => 'p.initialDate',
        'endDateAt' => 'p.endDateAt',
        'environment' => 'e.type',
        'provider' => 'p.provider',
    ];

    /**
     * @param string|null $environmentType PROD/TEST; null = todos los entornos
     * @param string $orderBy "<campo> <ASC|DESC>" — un campo fuera de la lista blanca cae al orden por defecto
     */
    public function getProducts(
        int $page = 0,
        int $limit = 10,
        ?string $environmentType = null,
        ?string $search = null,
        ?string $provider = null,
        string $orderBy = 'price ASC',
    ): IPaginationResponse {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('p')
            ->select('p', 'e')
            ->leftJoin('p.environment', 'e')
            ->where('p.endDateAt IS NULL OR p.endDateAt > :currentDate')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('enabled', true)
            ->setParameter('currentDate', $currentDate);
        if (!empty($environmentType)) {
            $dql->andWhere('e.type = :environmentType')->setParameter('environmentType', $environmentType);
        }
        if (!empty($search)) {
            $dql->andWhere('p.description LIKE :search')
                ->setParameter('search', '%' . str_replace('*', '%', $search) . '%');
        }
        if (!empty($provider)) {
            $dql->andWhere('p.provider = :provider')->setParameter('provider', $provider);
        }

        $orderParts = explode(' ', trim($orderBy));
        $field = self::SORTABLE_FIELDS[$orderParts[0]] ?? 'p.price';
        $direction = strtoupper($orderParts[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $dql->orderBy($field, $direction)->addOrderBy('p.id', 'ASC');

        $dql->setMaxResults($limit)->setFirstResult($page * $limit);
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);

        return new PaginatorResponse($page, $limit, $total, iterator_to_array($paginator));
    }

    /**
     * @param int $packageId
     * @param int $envId
     * @return CommunicationProduct[]
     */
    public function getProductsByPackageId(int $packageId, int $envId): array
    {
        $currentDate = new \DateTimeImmutable('now');
        $sql = $this->createQueryBuilder('c')
            ->leftJoin('c.environment', 'e')
            ->where('c.packageId = :cPackageId')
            ->andWhere('c.initialDate <= :currentDate')
            ->andWhere('c.endDateAt > :currentDate')
            ->andWhere('c.enabled = :enabled')
            ->andWhere('e.id = :env')
            ->setParameters(new ArrayCollection([
                new Parameter('cPackageId', $packageId),
                new Parameter('currentDate', $currentDate),
                new Parameter('env', $envId),
                new Parameter('enabled', true),
            ]));

        return $sql->getQuery()->getResult();
    }

    /**
     * Productos habilitados cuya tupla de destino coincide con
     * (destinationAmount, destinationCurrency) dentro de la tolerancia de
     * DestinationKey::EPSILON — el match agnóstico de proveedor entre
     * CommunicationPackage y CommunicationProduct (V2 Fase 2), usado tanto
     * por PackageCatalogResolver (MAX de precio, cualquier proveedor) como
     * por ProviderDispatchResolver (¿este proveedor concreto puede
     * despachar esta tupla?, vía $provider).
     *
     * @return list<CommunicationProduct>
     */
    public function findMatchingDestination(
        float $destinationAmount,
        string $destinationCurrency,
        ?Environment $environment = null,
        ?string $provider = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.enabled = :enabled')
            ->andWhere('UPPER(p.destinationUnit) = :currency')
            ->andWhere('p.destinationAmount BETWEEN :min AND :max')
            ->setParameter('enabled', true)
            ->setParameter('currency', strtoupper(trim($destinationCurrency)))
            ->setParameter('min', $destinationAmount - DestinationKey::EPSILON)
            ->setParameter('max', $destinationAmount + DestinationKey::EPSILON);

        if ($environment !== null) {
            $qb->andWhere('p.environment = :environment')->setParameter('environment', $environment);
        }
        if ($provider !== null) {
            $qb->andWhere('p.provider = :provider')->setParameter('provider', $provider);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Todos los productos HABILITADOS de un proveedor — fallback de
     * CommunicationPackageBindingService cuando findMatchingDestination()
     * no encuentra candidatos (ej. ETECSA: sus productos no traen
     * destinationAmount/destinationUnit poblados, así que el matching por
     * tupla nunca los encuentra) y el admin necesita elegir a mano de todo
     * el catálogo del proveedor.
     *
     * @return list<CommunicationProduct>
     */
    public function findAllByProvider(string $provider, ?Environment $environment = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.enabled = :enabled')
            ->andWhere('p.provider = :provider')
            ->setParameter('enabled', true)
            ->setParameter('provider', $provider)
            ->orderBy('p.description', 'ASC');

        if ($environment !== null) {
            $qb->andWhere('p.environment = :environment')->setParameter('environment', $environment);
        }

        return $qb->getQuery()->getResult();
    }
}
