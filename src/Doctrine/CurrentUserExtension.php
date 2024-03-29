<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Account;
use App\Entity\BankCard;
use App\Entity\City;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProvinces;
use App\Entity\Country;
use App\Entity\Province;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $queryNameGenerator);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $queryNameGenerator);
    }

    public function addWhere(
        QueryBuilder $queryBuilder,
        string $resourceClass,
        QueryNameGeneratorInterface $queryNameGenerator = null
    ): void {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_API_USER')) {
            return;
        }

        if ($user instanceof Account) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            if (in_array(
                $resourceClass,
                [
                    Country::class,
                    Province::class,
                    City::class,
                    CommunicationNationality::class,
                    CommunicationProvinces::class,
                    CommunicationOffice::class
                ]
            )) {
                $environment = $user->getEnvironment();
                $queryBuilder->andWhere(sprintf('%s.environment = :env', $rootAlias));
                $queryBuilder->setParameter('env', $environment?->getId());
            } elseif (BankCard::class === $resourceClass) {
                $queryBuilder->innerJoin(sprintf('%s.beneficiary', $rootAlias), 'b')
                    ->andWhere('b.tenant = :current_user')
                    ->setParameter('current_user', $user->getId());
            } else {
                $queryBuilder->andWhere(sprintf('%s.tenant = :current_user', $rootAlias));
                $queryBuilder->setParameter('current_user', $user->getId());
                if ($resourceClass == CommunicationPackage::class) {
                    $queryBuilder->andWhere(sprintf('%s.isEnabled = :sIsEnabled', $rootAlias))
                        ->setParameter('sIsEnabled', true)
                        ->addOrderBy(sprintf('%s.comId', $rootAlias))
                        ->addOrderBy(sprintf('%s.amount', $rootAlias));

                }
            }
        }
    }
}
