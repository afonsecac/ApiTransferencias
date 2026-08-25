<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Service\Pricing\DestinationKey;
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
     * Contratos propios vigentes de un tenant, con sus CommunicationPackage
     * ya cargados (fetch join sobre la colección — Doctrine hidrata UN
     * CommunicationContract por fila lógica, con su colección poblada, no
     * uno por paquete). Usado por PackageCatalogResolver::catalogFor()
     * (Fase 2): si esto no está vacío, el catálogo visible del cliente es
     * EXACTAMENTE los paquetes de estos contratos.
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

    /**
     * Contratos propios vigentes que YA cubren un paquete dado — usado para
     * encontrar, dado el paquete regular equivalente a uno de promoción,
     * qué tenants tienen contrato propio sobre él (ver
     * CommunicationContractService::linkTenantContractsToPromotionPackage()).
     *
     * @return list<CommunicationContract>
     */
    public function findActiveTenantContractsForPackage(CommunicationPackage $package, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->baseActiveQueryBuilder($now)
            ->andWhere('c.tenant IS NOT NULL')
            ->andWhere(':package MEMBER OF c.packages')
            ->setParameter('package', $package)
            ->getQuery()
            ->getResult();
    }

    private function baseActiveQueryBuilder(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.packages', 'p')
            ->andWhere('c.startAt <= :now')
            ->andWhere('c.endAt IS NULL OR c.endAt > :now')
            ->setParameter('now', $now)
            ->orderBy('c.startAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');
    }

    /**
     * El contrato ABIERTO (endAt IS NULL) para esta tupla+categoría —
     * tolerancia de coma flotante igual que
     * CommunicationPackageRepository::findByDestination()
     * (DestinationKey::EPSILON). Es la clave de deduplicación de
     * CommunicationContractService::upsertContract(): dos paquetes con la
     * misma tupla+tenant+categoría terminan en el contrato que esto
     * devuelve — desde la Fase 3 del rediseño por categoría, `$serviceKey`
     * es parte de esta identidad (índice único
     * uniq_com_contract_open_per_tenant_amount incluye service_key), así
     * que dos paquetes que comparten tupla pero difieren en categoría
     * producen DOS contratos distintos, no uno solo mezclado.
     */
    public function findOpenContract(?Account $tenant, float $amount, string $currency, string $serviceKey): ?CommunicationContract
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.destinationAmount BETWEEN :min AND :max')
            ->andWhere('c.destinationCurrency = :currency')
            ->andWhere('c.serviceKey = :serviceKey')
            ->andWhere('c.endAt IS NULL')
            ->setParameter('min', $amount - DestinationKey::EPSILON)
            ->setParameter('max', $amount + DestinationKey::EPSILON)
            ->setParameter('currency', strtoupper($currency))
            ->setParameter('serviceKey', $serviceKey)
            ->setMaxResults(1);

        if ($tenant === null) {
            $qb->andWhere('c.tenant IS NULL');
        } else {
            $qb->andWhere('c.tenant = :tenant')->setParameter('tenant', $tenant);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
