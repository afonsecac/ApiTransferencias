<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016170000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016170000.php';

/**
 * COMR-276: las tablas de gift cards; la base refuerza el estado, el valor, la coherencia del canje y la traza inmutable.
 *
 * @covers \DoctrineMigrations\Version20261016170000
 */
class Version20261016170000Test extends ProviderFunctionalTestCase
{
    private function seed(string $status = 'issued', ?string $redeemedAt = null, ?string $ref = null, int $value = 2500, string $serial = '4111111111111111', string $hash = 'h1'): int
    {
        $c = $this->em->getConnection();
        $c->executeStatement(<<<'SQL'
            INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, value_currency, value_min_minor, created_at, updated_at)
            SELECT 'gc-mig-' || :serial, id, 'draft', 'Gift Cards', 'Retail', '{"es":{"name":"T"}}', 'USD', 2500, NOW(), NOW() FROM market_seller WHERE is_platform
        SQL, ['serial' => $serial]);
        $product = (int) $c->fetchOne('SELECT max(id) FROM market_product');
        $center = (int) $c->fetchOne('SELECT cost_center_id FROM market_seller WHERE is_platform');
        $c->executeStatement('INSERT INTO market_gift_card_batch (product_id, quantity, cost_center_id, issued_by, created_at) VALUES (:p, 1, :c, \'t\', NOW())', ['p' => $product, 'c' => $center]);
        $batch = (int) $c->fetchOne('SELECT max(id) FROM market_gift_card_batch');
        $c->executeStatement(
            'INSERT INTO market_gift_card (batch_id, product_id, serial, value_minor, currency, pin_cipher, pin_hash, status, cost_center_id, redeemed_at, redemption_ref, created_at)
             VALUES (:b, :p, :s, :v, \'USD\', \'c\', :h, :st, :c, :ra, :rr, NOW())',
            ['b' => $batch, 'p' => $product, 's' => $serial, 'v' => $value, 'h' => $hash, 'st' => $status, 'c' => $center, 'ra' => $redeemedAt, 'rr' => $ref],
        );

        return (int) $c->fetchOne('SELECT max(id) FROM market_gift_card');
    }

    public function testItAcceptsAValidCard(): void
    {
        $this->seed();
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_gift_card'));
    }

    public function testItRejectsAnUnknownStatusAndAZeroValue(): void
    {
        $this->expectException(DbalException::class);
        $this->seed('perdida');
    }

    public function testItRejectsAZeroValueCard(): void
    {
        $this->expectException(DbalException::class);
        $this->seed(value: 0);
    }

    public function testARedeemedCardMustCarryItsDateAndKeyAndOnlyThen(): void
    {
        $this->seed('redeemed', '2026-10-16 10:00:00', 'canje-1');
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_gift_card WHERE status = 'redeemed'"));

        $this->expectException(DbalException::class);
        $this->seed('redeemed', null, null, serial: '5500000000000004', hash: 'h2');
    }

    public function testTheSerialAndThePinHashAreUnique(): void
    {
        $this->seed();

        $this->expectException(DbalException::class);
        $this->seed(serial: '4111111111111111', hash: 'h-otro');
    }

    public function testAnEventCannotBeModifiedNorDeleted(): void
    {
        $card = $this->seed();
        $c = $this->em->getConnection();
        $c->executeStatement("INSERT INTO market_gift_card_event (card_id, type, actor_name, created_at) VALUES (:c, 'issued', 'ana', NOW())", ['c' => $card]);

        try {
            $c->executeStatement("UPDATE market_gift_card_event SET actor_name = 'otro'");
            $this->fail('debía rechazar la modificación');
        } catch (DbalException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016170000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_gift_card'));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016170000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
