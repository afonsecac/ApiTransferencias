<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPromotions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UpcomingPackagesProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            return [];
        }

        $now = new \DateTimeImmutable();

        $packages = $this->em->getRepository(CommunicationClientPackage::class)
            ->createQueryBuilder('cp')
            ->join('cp.promotionItems', 'p')
            ->where('cp.tenant = :tenant')
            ->andWhere('p.startAt > :now')
            ->setParameter('tenant', $user)
            ->setParameter('now', $now)
            ->orderBy('p.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($packages)) {
            return [];
        }

        $packageIds = array_map(fn(CommunicationClientPackage $cp) => $cp->getId(), $packages);

        // Cargamos las promociones futuras desde el lado propietario (CommunicationPromotions.products)
        $promotions = $this->em->createQueryBuilder()
            ->select('p')
            ->from(CommunicationPromotions::class, 'p')
            ->join('p.products', 'cp')
            ->where('cp.id IN (:ids)')
            ->andWhere('p.startAt > :now')
            ->setParameter('ids', $packageIds)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        // Mapeamos promotion → packages del conjunto cargado
        $packagesById = [];
        foreach ($packages as $pkg) {
            $packagesById[$pkg->getId()] = $pkg;
        }

        $promosByPackage = [];
        foreach ($promotions as $promotion) {
            foreach ($promotion->getProducts() as $pkg) {
                if (isset($packagesById[$pkg->getId()])) {
                    $promosByPackage[$pkg->getId()][] = $promotion;
                }
            }
        }

        foreach ($packages as $pkg) {
            $pkg->setUpcomingPromotions($promosByPackage[$pkg->getId()] ?? []);
        }

        return $packages;
    }
}
