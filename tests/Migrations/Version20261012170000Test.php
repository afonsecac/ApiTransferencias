<?php

namespace App\Tests\Migrations;

use App\Enums\CommunicationStateEnum;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261012170000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261012170000.php';

/**
 * COMR-260, paso 1: columnas next_check_at y check_attempts en la venta, con backfill de next_check_at
 * para las PENDING existentes (si no, una consulta por next_check_at <= ahora dejaría de verlas).
 *
 * @covers \DoctrineMigrations\Version20261012170000
 */
class Version20261012170000Test extends ProviderFunctionalTestCase
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
        $sale = (new \App\Entity\CommunicationSaleRecharge())
            ->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('mig260-' . $n)
            ->setTransactionId('26101400000' . $n)
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('UPDATE communication_sale_info SET next_check_at = NULL, check_attempts = 0 WHERE id = :id', ['id' => $sale->getId()]);

        return (int) $sale->getId();
    }

    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261012170000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $this->em->clear();
    }

    private function nextCheckAtOf(int $id): ?string
    {
        $value = $this->em->getConnection()->fetchOne('SELECT next_check_at FROM communication_sale_info WHERE id = :id', ['id' => $id]);

        return false === $value ? null : $value;
    }

    public function testPendingSalesGetANextCheckAtSoTheyKeepBeingPolled(): void
    {
        $pending = $this->sale(CommunicationStateEnum::PENDING, 'Pending', 1);

        $this->runMigration();

        $this->assertNotNull($this->nextCheckAtOf($pending));
    }

    public function testSalesThatAreNotAwaitingAStatusCheckAreLeftWithoutNextCheckAt(): void
    {
        $completed = $this->sale(CommunicationStateEnum::COMPLETED, 'Completed', 2);
        $rejected = $this->sale(CommunicationStateEnum::REJECTED, 'Rejected', 3);
        $failed = $this->sale(CommunicationStateEnum::FAILED, 'Failed', 4);

        $this->runMigration();

        $this->assertNull($this->nextCheckAtOf($completed));
        $this->assertNull($this->nextCheckAtOf($rejected));
        $this->assertNull($this->nextCheckAtOf($failed));
    }

    public function testRunningItTwiceKeepsTheFirstBackfill(): void
    {
        $pending = $this->sale(CommunicationStateEnum::PENDING, 'Pending', 5);

        $this->runMigration();
        $first = $this->nextCheckAtOf($pending);
        $this->runMigration();

        $this->assertSame($first, $this->nextCheckAtOf($pending));
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261012170000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
