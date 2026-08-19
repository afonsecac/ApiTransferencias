<?php

namespace App\Repository;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
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
     * Paquetes que entrarán en vigencia a futuro (típicamente tramos de una
     * promoción V2 aún no arrancada) — alimenta
     * /communication/packages/upcoming para cuentas V2
     * (UpcomingPackageCatalogResolver). Complemento exacto de
     * findActiveCatalog(): un paquete nunca puede salir en las dos a la vez.
     * Sin filtro de tenant: el catálogo V2 es compartido.
     *
     * @return list<CommunicationPackage>
     */
    public function findUpcoming(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.activeStartAt > :now')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('p.activeStartAt', 'ASC')
            ->addOrderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paquete cuyo destino coincide con la tupla dada, con tolerancia de
     * coma flotante (DestinationKey::EPSILON) — usado por
     * CommunicationContractService::createByRange() para resolver, monto a
     * monto, a qué CommunicationPackage corresponde cada paso del rango.
     *
     * Excluye SIEMPRE los paquetes generados por una promoción
     * (promotion IS NOT NULL, ver CommunicationPackage::$promotion) — son
     * copias con vigencia acotada, no catálogo regular; el admin de
     * contratos no debe poder engancharse a uno por coincidencia de monto.
     * Para resolver dentro del lote de una promoción concreta, ver
     * findByDestinationForPromotion().
     */
    public function findByDestination(float $amount, string $currency): ?CommunicationPackage
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.destinationAmount BETWEEN :min AND :max')
            ->andWhere('p.destinationCurrency = :currency')
            ->andWhere('p.promotion IS NULL')
            ->setParameter('min', $amount - DestinationKey::EPSILON)
            ->setParameter('max', $amount + DestinationKey::EPSILON)
            ->setParameter('currency', strtoupper($currency))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Todos los tramos generados por una promoción V2, ordenados por monto
     * — usado por CommunicationPromotionEquivalenceService (Fase 5D) para
     * poblar/refrescar las equivalencias por proveedor sin depender de
     * tener la lista en memoria (ej. acción manual "refrescar" sobre una
     * promoción ya creada en una petición anterior).
     *
     * @return list<CommunicationPackage>
     */
    public function findByPromotion(CommunicationPromotions $promotion): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.promotion = :promotion')
            ->setParameter('promotion', $promotion)
            ->orderBy('p.destinationAmount', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paquete de una promoción V2 aún no vigente, para
     * CommunicationSaleService::processReserve() (Fase 5) — equivalente V2
     * de CommunicationPromotionsRepository::getFuturePromotionById(), que
     * solo cubre CommunicationClientPackage (V1) vía
     * CommunicationPromotions::$products, un ManyToMany que nunca incluye
     * CommunicationPackage (aquí la relación es el ManyToOne directo
     * CommunicationPackage::$promotion). Mismo criterio "futuro":
     * promo.startAt > $now.
     */
    public function findFutureForReserve(int $packageId, int $promotionId, ?\DateTimeImmutable $now = null): ?CommunicationPackage
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->innerJoin('p.promotion', 'promo')
            ->andWhere('p.id = :packageId')
            ->andWhere('promo.id = :promotionId')
            ->andWhere('promo.startAt > :now')
            ->setParameter('packageId', $packageId)
            ->setParameter('promotionId', $promotionId)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Variante de findByDestination() acotada a los paquetes generados por
     * UNA promoción concreta (Fase 5B) — misma tolerancia de coma flotante,
     * pero nunca puede devolver un paquete del catálogo regular ni de otra
     * promoción, aunque compartan tupla destino.
     */
    public function findByDestinationForPromotion(float $amount, string $currency, CommunicationPromotions $promotion): ?CommunicationPackage
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.destinationAmount BETWEEN :min AND :max')
            ->andWhere('p.destinationCurrency = :currency')
            ->andWhere('p.promotion = :promotion')
            ->setParameter('min', $amount - DestinationKey::EPSILON)
            ->setParameter('max', $amount + DestinationKey::EPSILON)
            ->setParameter('currency', strtoupper($currency))
            ->setParameter('promotion', $promotion)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
