<?php

namespace App\Tests\Functional\Pricing;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Repository\CommunicationPackageRepository;
use App\Tests\Functional\FunctionalTestCase;

/**
 * @covers \App\Repository\CommunicationPackageRepository
 */
class CommunicationPackageRepositoryTest extends FunctionalTestCase
{
    private static int $counter = 0;

    private function repository(): CommunicationPackageRepository
    {
        return self::getContainer()->get(CommunicationPackageRepository::class);
    }

    private function package(
        bool $isActive = true,
        ?\DateTimeImmutable $activeStartAt = null,
        ?\DateTimeImmutable $activeEndAt = null,
        int $displayOrder = 0,
    ): CommunicationPackage {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName("Paquete {$this::$counter}")
            ->setDescription("Paquete {$this::$counter}")
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setIsActive($isActive)
            ->setDisplayOrder($displayOrder);

        if ($activeStartAt !== null) {
            $package->setActiveStartAt($activeStartAt);
        }
        if ($activeEndAt !== null) {
            $package->setActiveEndAt($activeEndAt);
        }

        $this->em->persist($package);

        return $package;
    }

    public function testFindActiveCatalogExcludesInactivePackages(): void
    {
        $now = new \DateTimeImmutable();
        $active = $this->package(isActive: true, activeStartAt: $now->modify('-1 day'));
        $this->package(isActive: false, activeStartAt: $now->modify('-1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveCatalog($now);

        $this->assertCount(1, $result);
        $this->assertSame($active->getId(), $result[0]->getId());
    }

    public function testFindActiveCatalogExcludesPackagesNotYetStarted(): void
    {
        $now = new \DateTimeImmutable();
        $this->package(activeStartAt: $now->modify('+1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveCatalog($now);

        $this->assertSame([], $result);
    }

    public function testFindActiveCatalogExcludesPackagesAlreadyEnded(): void
    {
        $now = new \DateTimeImmutable();
        $this->package(activeStartAt: $now->modify('-2 days'), activeEndAt: $now->modify('-1 day'));
        $this->em->flush();

        $result = $this->repository()->findActiveCatalog($now);

        $this->assertSame([], $result);
    }

    public function testFindActiveCatalogIncludesPackagesWithNoEndDate(): void
    {
        $now = new \DateTimeImmutable();
        $package = $this->package(activeStartAt: $now->modify('-1 day'), activeEndAt: null);
        $this->em->flush();

        $result = $this->repository()->findActiveCatalog($now);

        $this->assertCount(1, $result);
        $this->assertSame($package->getId(), $result[0]->getId());
    }

    public function testFindActiveCatalogOrdersByDisplayOrderThenId(): void
    {
        $now = new \DateTimeImmutable();
        $second = $this->package(activeStartAt: $now->modify('-1 day'), displayOrder: 10);
        $first = $this->package(activeStartAt: $now->modify('-1 day'), displayOrder: 0);
        // Mismo displayOrder que $first: desempate por id ASC (orden de creación).
        $tieBreak = $this->package(activeStartAt: $now->modify('-1 day'), displayOrder: 0);
        $this->em->flush();

        $result = $this->repository()->findActiveCatalog($now);

        $this->assertSame(
            [$first->getId(), $tieBreak->getId(), $second->getId()],
            array_map(static fn (CommunicationPackage $p) => $p->getId(), $result),
        );
    }

    public function testFindUpcomingExcludesInactivePackages(): void
    {
        $now = new \DateTimeImmutable();
        $this->package(isActive: false, activeStartAt: $now->modify('+1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findUpcoming($now));
    }

    public function testFindUpcomingExcludesPackagesAlreadyActive(): void
    {
        $now = new \DateTimeImmutable();
        $this->package(activeStartAt: $now->modify('-1 day'));
        $this->em->flush();

        $this->assertSame([], $this->repository()->findUpcoming($now));
    }

    public function testFindUpcomingIncludesFuturePackages(): void
    {
        $now = new \DateTimeImmutable();
        $future = $this->package(activeStartAt: $now->modify('+1 day'));
        $this->em->flush();

        $result = $this->repository()->findUpcoming($now);

        $this->assertCount(1, $result);
        $this->assertSame($future->getId(), $result[0]->getId());
    }

    public function testFindUpcomingOrdersByActiveStartAtThenDisplayOrderThenId(): void
    {
        $now = new \DateTimeImmutable();
        $later = $this->package(activeStartAt: $now->modify('+3 days'));
        $second = $this->package(activeStartAt: $now->modify('+1 day'), displayOrder: 10);
        $first = $this->package(activeStartAt: $now->modify('+1 day'), displayOrder: 0);
        $this->em->flush();

        $result = $this->repository()->findUpcoming($now);

        $this->assertSame(
            [$first->getId(), $second->getId(), $later->getId()],
            array_map(static fn (CommunicationPackage $p) => $p->getId(), $result),
        );
    }

    private function promotion(\DateTimeImmutable $startAt, \DateTimeImmutable $endAt): CommunicationPromotions
    {
        self::$counter++;
        $promotion = (new CommunicationPromotions())
            ->setName("Promo {$this::$counter}")
            ->setDescription("Promo {$this::$counter}")
            ->setStartAt($startAt)
            ->setEndAt($endAt);

        $this->em->persist($promotion);

        return $promotion;
    }

    public function testFindFutureForReserveReturnsPackageWhenPromotionHasNotStarted(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('+1 day'), $now->modify('+30 days'));
        $package = $this->package(activeStartAt: $now->modify('+1 day'))->setPromotion($promotion);
        $this->em->flush();

        $result = $this->repository()->findFutureForReserve($package->getId(), $promotion->getId(), $now);

        $this->assertNotNull($result);
        $this->assertSame($package->getId(), $result->getId());
    }

    public function testFindFutureForReserveReturnsNullWhenPromotionAlreadyStarted(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $package = $this->package(activeStartAt: $now->modify('-1 day'))->setPromotion($promotion);
        $this->em->flush();

        $this->assertNull($this->repository()->findFutureForReserve($package->getId(), $promotion->getId(), $now));
    }

    public function testFindFutureForReserveReturnsNullWhenPackageBelongsToAnotherPromotion(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('+1 day'), $now->modify('+30 days'));
        $otherPromotion = $this->promotion($now->modify('+1 day'), $now->modify('+30 days'));
        $package = $this->package(activeStartAt: $now->modify('+1 day'))->setPromotion($promotion);
        $this->em->flush();

        $this->assertNull($this->repository()->findFutureForReserve($package->getId(), $otherPromotion->getId(), $now));
    }

    public function testFindFutureForReserveReturnsNullWhenPackageHasNoPromotion(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('+1 day'), $now->modify('+30 days'));
        $package = $this->package(activeStartAt: $now->modify('+1 day'));
        $this->em->flush();

        $this->assertNull($this->repository()->findFutureForReserve($package->getId(), $promotion->getId(), $now));
    }
}
