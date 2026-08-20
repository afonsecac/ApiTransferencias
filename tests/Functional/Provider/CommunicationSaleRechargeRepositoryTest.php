<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleRechargeRepository;

/**
 * @covers \App\Repository\CommunicationSaleRechargeRepository::getCurrentActivePromotionsReserves
 *
 * Fase 5 (reserva de promociones V2): la consulta original hacía un INNER
 * JOIN sobre r.package (CommunicationClientPackage, V1) — una reserva V2
 * (r.catalogPackage, r.package siempre NULL) se descartaba en silencio,
 * nunca se activaba aunque su promoción ya estuviera vigente
 * (SaleRechargeTask corriendo cada 5 minutos sin efecto sobre ella).
 */
class CommunicationSaleRechargeRepositoryTest extends ProviderFunctionalTestCase
{
    private static int $counter = 0;

    private function repository(): CommunicationSaleRechargeRepository
    {
        return self::getContainer()->get(CommunicationSaleRechargeRepository::class);
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

    private function reserve(CommunicationPromotions $promotion): CommunicationSaleRecharge
    {
        self::$counter++;
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $recharge = (new CommunicationSaleRecharge())
            ->setPhoneNumber('5350000' . self::$counter)
            ->setClientTransactionId("ctx-reserve-{$this::$counter}")
            ->setTransactionId("reserve-{$this::$counter}")
            ->setTenant($account)
            ->setProvider('ETECSA')
            ->setAmount(10.0)
            ->setCurrency('USD')
            ->setState(CommunicationStateEnum::RESERVED)
            ->setStateProcess(CommunicationStateEnum::CREATED->value)
            ->setPromotion($promotion);
        $recharge->setTotalPrice(10.0);

        $this->em->persist($recharge);

        return $recharge;
    }

    private function legacyPackage(?\DateTimeImmutable $activeEndAt): CommunicationClientPackage
    {
        $package = (new CommunicationClientPackage())
            ->setName('Paquete legacy')
            ->setDescription('Paquete legacy')
            ->setAmount(10.0)
            ->setCurrency('USD')
            ->setActiveEndAt($activeEndAt ?? new \DateTimeImmutable('+1 year'));
        $this->em->persist($package);

        return $package;
    }

    private function catalogPackage(?\DateTimeImmutable $activeEndAt): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName('Paquete V2')
            ->setDescription('Paquete V2')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');
        if ($activeEndAt !== null) {
            $package->setActiveEndAt($activeEndAt);
        }
        $this->em->persist($package);

        return $package;
    }

    public function testIncludesAV2ReserveWhoseCatalogPackageHasNoEndDate(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $catalogPackage = $this->catalogPackage(null);
        $reserve = $this->reserve($promotion)->setCatalogPackage($catalogPackage);
        $this->em->flush();

        $result = $this->repository()->getCurrentActivePromotionsReserves();

        $this->assertCount(1, $result);
        $this->assertSame($reserve->getId(), $result[0]->getId());
    }

    public function testIncludesAV2ReserveWhoseCatalogPackageEndsInTheFuture(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $catalogPackage = $this->catalogPackage($now->modify('+30 days'));
        $reserve = $this->reserve($promotion)->setCatalogPackage($catalogPackage);
        $this->em->flush();

        $result = $this->repository()->getCurrentActivePromotionsReserves();

        $this->assertCount(1, $result);
        $this->assertSame($reserve->getId(), $result[0]->getId());
    }

    public function testExcludesAV2ReserveWhoseCatalogPackageAlreadyEnded(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $catalogPackage = $this->catalogPackage($now->modify('-1 day'));
        $this->reserve($promotion)->setCatalogPackage($catalogPackage);
        $this->em->flush();

        $this->assertSame([], $this->repository()->getCurrentActivePromotionsReserves());
    }

    public function testIncludesALegacyReserveWhosePackageEndsInTheFuture(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $legacyPackage = $this->legacyPackage($now->modify('+1 year'));
        $reserve = $this->reserve($promotion)->setPackage($legacyPackage);
        $reserve->setPackageId($legacyPackage->getId() ?? 1);
        $this->em->flush();

        $result = $this->repository()->getCurrentActivePromotionsReserves();

        $this->assertCount(1, $result);
        $this->assertSame($reserve->getId(), $result[0]->getId());
    }

    public function testExcludesALegacyReserveWhosePackageAlreadyEnded(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $legacyPackage = $this->legacyPackage($now->modify('-1 day'));
        $reserve = $this->reserve($promotion)->setPackage($legacyPackage);
        $reserve->setPackageId($legacyPackage->getId() ?? 1);
        $this->em->flush();

        $this->assertSame([], $this->repository()->getCurrentActivePromotionsReserves());
    }

    public function testExcludesAReserveWhosePromotionHasNotStartedYet(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('+1 day'), $now->modify('+30 days'));
        $catalogPackage = $this->catalogPackage(null);
        $this->reserve($promotion)->setCatalogPackage($catalogPackage);
        $this->em->flush();

        $this->assertSame([], $this->repository()->getCurrentActivePromotionsReserves());
    }

    public function testExcludesAReserveThatIsNoLongerInReservedState(): void
    {
        $now = new \DateTimeImmutable();
        $promotion = $this->promotion($now->modify('-1 day'), $now->modify('+30 days'));
        $catalogPackage = $this->catalogPackage(null);
        $this->reserve($promotion)->setCatalogPackage($catalogPackage)->setState(CommunicationStateEnum::PENDING);
        $this->em->flush();

        $this->assertSame([], $this->repository()->getCurrentActivePromotionsReserves());
    }
}
