<?php

namespace App\Repository;

use App\Entity\User;
use App\EntityPaginator\PaginatorResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function searchAllUsersInCompany(?int $companyId = null, ?bool $isActive = null, int $page = 0, int $limit = 20): PaginatorResponse
    {
        $dql = $this->createQueryBuilder('u')
            ->leftJoin('u.company', 'c');

        if ($companyId !== null) {
            $dql->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }

        if ($isActive !== null) {
            $dql->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        $dql->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy('c.companyName');
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);
        return new PaginatorResponse($page, $limit, $total, $paginator->getQuery()->getResult());
    }
}
