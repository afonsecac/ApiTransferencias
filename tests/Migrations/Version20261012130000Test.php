<?php

namespace App\Tests\Migrations;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261012130000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261012130000.php';

/**
 * COMR-261: repara las ventas rechazadas que quedaron con state process Pending. La migración corrige
 * solo esa incoherencia exacta (state Rejected + state process Pending) y es idempotente.
 *
 * @covers \DoctrineMigrations\Version20261012130000
 */
class Version20261012130000Test extends ProviderFunctionalTestCase
{
    private \App\Entity\Account $account;
    private \App\Entity\CommunicationPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $environment = $this->createEnvironment();
        $this->account = $this->createAccount($this->createClient(), $environment);
        $this->package = $this->createSellableV2Package($environment, 'ETECSA');
    }

    private function sale(CommunicationStateEnum $state, string $stateProcess, int $n): int
    {
        $account = $this->account;
        $package = $this->package;
        $sale = (new \App\Entity\CommunicationSaleRecharge())
            ->setTenant($account)
            ->setCatalogPackage($package)
            ->setPackageId($package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('mig-' . $n)
            ->setTransactionId('26101300000' . $n)
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);
        $this->em->flush();

        return (int) $sale->getId();
    }

    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261012130000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $this->em->clear();
    }

    private function stateProcessOf(int $id): ?string
    {
        return $this->em->getRepository(CommunicationSaleInfo::class)->find($id)?->getStateProcess();
    }

    public function testRejectedSalesWithPendingStateProcessBecomeRejected(): void
    {
        $inconsistent = $this->sale(CommunicationStateEnum::REJECTED, 'Pending', 1);

        $this->runMigration();

        $this->assertSame('Rejected', $this->stateProcessOf($inconsistent));
    }

    public function testNothingElseIsTouched(): void
    {
        $pending = $this->sale(CommunicationStateEnum::PENDING, 'Pending', 2);
        $completed = $this->sale(CommunicationStateEnum::COMPLETED, 'Completed', 3);
        $rejectedOk = $this->sale(CommunicationStateEnum::REJECTED, 'Rejected', 4);

        $this->runMigration();

        $this->assertSame('Pending', $this->stateProcessOf($pending));
        $this->assertSame('Completed', $this->stateProcessOf($completed));
        $this->assertSame('Rejected', $this->stateProcessOf($rejectedOk));
    }

    public function testRunningItTwiceChangesNothingTheSecondTime(): void
    {
        $inconsistent = $this->sale(CommunicationStateEnum::REJECTED, 'Pending', 5);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame('Rejected', $this->stateProcessOf($inconsistent));
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261012130000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
