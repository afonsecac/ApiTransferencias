<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
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
}
