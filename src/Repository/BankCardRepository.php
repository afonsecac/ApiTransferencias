<?php

namespace App\Repository;

use App\Entity\BankCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankCard>
 *
 * @method BankCard|null find($id, $lockMode = null, $lockVersion = null)
 * @method BankCard|null findOneBy(array $criteria, array $orderBy = null)
 * @method BankCard[]    findAll()
 * @method BankCard[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BankCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankCard::class);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getBeneficiaryCard(int $id): BankCard | null
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.beneficiary', 'a')
            ->where('b.id = :bId OR a.id = :bId')
            ->andWhere('a.isActive = :aIsActive')
            ->setMaxResults(1)
            ->setParameters(new ArrayCollection([
                new Parameter('bId', $id),
                new Parameter('aIsActive', true)
            ]))
            ->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

}
