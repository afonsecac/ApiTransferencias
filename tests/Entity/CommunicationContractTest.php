<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Exception\MyCurrentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\CommunicationContract
 */
class CommunicationContractTest extends TestCase
{
    private function contract(): CommunicationContract
    {
        $package = (new CommunicationPackage())
            ->setName('Paquete')
            ->setDescription('Paquete')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');

        return (new CommunicationContract())
            ->addPackage($package)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(10.0)
            ->setCurrency('USD');
    }

    private function packageInCategory(string $name, string $serviceName, string $subserviceName): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName($name)
            ->setDescription($name)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');
        $package->setService(['name' => $serviceName, 'subservice' => ['name' => $subserviceName]]);

        return $package;
    }

    public function testIsActiveAtWithinOpenEndedWindow(): void
    {
        $now = new \DateTimeImmutable('2026-08-10 12:00:00');
        $contract = $this->contract()->setStartAt($now->modify('-1 day'));

        $this->assertTrue($contract->isActiveAt($now));
    }

    public function testIsActiveAtFalseBeforeStart(): void
    {
        $now = new \DateTimeImmutable('2026-08-10 12:00:00');
        $contract = $this->contract()->setStartAt($now->modify('+1 day'));

        $this->assertFalse($contract->isActiveAt($now));
    }

    public function testIsActiveAtFalseAfterEnd(): void
    {
        $now = new \DateTimeImmutable('2026-08-10 12:00:00');
        $contract = $this->contract()
            ->setStartAt($now->modify('-2 days'))
            ->setEndAt($now->modify('-1 day'));

        $this->assertFalse($contract->isActiveAt($now));
    }

    public function testIsActiveAtTrueExactlyAtEndIsExclusive(): void
    {
        $now = new \DateTimeImmutable('2026-08-10 12:00:00');
        $contract = $this->contract()
            ->setStartAt($now->modify('-1 day'))
            ->setEndAt($now);

        // endAt es exclusivo: vigente hasta antes de endAt, no en endAt.
        $this->assertFalse($contract->isActiveAt($now));
        $this->assertTrue($contract->isActiveAt($now->modify('-1 second')));
    }

    public function testCloseSetsEndAtToNowByDefault(): void
    {
        $contract = $this->contract();
        $this->assertNull($contract->getEndAt());

        $before = new \DateTimeImmutable();
        $contract->close();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($contract->getEndAt());
        $this->assertGreaterThanOrEqual($before, $contract->getEndAt());
        $this->assertLessThanOrEqual($after, $contract->getEndAt());
    }

    public function testCloseAcceptsAnExplicitTimestamp(): void
    {
        $at = new \DateTimeImmutable('2026-01-01 00:00:00');
        $contract = $this->contract()->close($at);

        $this->assertSame($at, $contract->getEndAt());
    }

    public function testAddPackageIsIdempotent(): void
    {
        $contract = new CommunicationContract();
        $package = (new CommunicationPackage())->setName('p')->setDescription('p')->setDestinationAmount(500.0)->setDestinationCurrency('CUP');

        $contract->addPackage($package);
        $contract->addPackage($package);

        $this->assertCount(1, $contract->getPackages());
        $this->assertTrue($contract->getPackages()->contains($package));
    }

    public function testRemovePackage(): void
    {
        $contract = $this->contract();
        $package = $contract->getPackages()->first();

        $contract->removePackage($package);

        $this->assertCount(0, $contract->getPackages());
    }

    // ---- Fase 3: la categoría del contrato es parte de su identidad ----

    public function testAddPackageAllowsTheFirstPackageRegardlessOfCategoryOrdering(): void
    {
        // El primer paquete nunca se rechaza a sí mismo — necesario porque
        // muchos constructores de test (y upsertContract() en algunos
        // casos) agregan el paquete antes o después de fijar la categoría
        // del contrato indistintamente.
        $package = $this->packageInCategory('Recarga', 'Mobile', 'AIRTIME');
        $contract = new CommunicationContract();

        $contract->addPackage($package);

        $this->assertCount(1, $contract->getPackages());
    }

    public function testAddPackageRejectsASecondPackageOfADifferentCategory(): void
    {
        $mobile = $this->packageInCategory('Recarga', 'Mobile', 'AIRTIME');
        $utilities = $this->packageInCategory('Nauta', 'Utilities', 'INTERNET');

        $contract = (new CommunicationContract())->setServiceCategory('Mobile', 'AIRTIME');
        $contract->addPackage($mobile);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('categoría distinta');

        $contract->addPackage($utilities);
    }

    public function testAddPackageAllowsASecondPackageOfTheSameCategory(): void
    {
        $a = $this->packageInCategory('Recarga A', 'Mobile', 'AIRTIME');
        $b = $this->packageInCategory('Recarga B', 'Mobile', 'AIRTIME');

        $contract = (new CommunicationContract())->setServiceCategory('Mobile', 'AIRTIME');
        $contract->addPackage($a);
        $contract->addPackage($b);

        $this->assertCount(2, $contract->getPackages());
    }

    public function testAddPackageReaddingTheSamePackageNeverTripsTheGuard(): void
    {
        // contains() ya lo intercepta antes de evaluar la categoría —
        // re-agregar el mismo paquete es siempre un no-op, nunca un rechazo.
        $package = $this->packageInCategory('Recarga', 'Mobile', 'AIRTIME');
        $contract = (new CommunicationContract())->setServiceCategory('Mobile', 'AIRTIME');
        $contract->addPackage($package);

        $contract->addPackage($package);

        $this->assertCount(1, $contract->getPackages());
    }
}
