<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Infrastructure;

use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Infrastructure\Http\AdjustStockDto;
use App\Market\Inventario\Infrastructure\Http\CreateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\SetSafetyStockDto;
use App\Market\Inventario\Infrastructure\Http\StockController;
use App\Market\Inventario\Infrastructure\Http\WarehouseController;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-194 contra Postgres real: el ajuste de stock, su movimiento inmutable y el contrato HTTP.
 *
 * @covers \App\Market\Inventario\Infrastructure\Persistence\DoctrineStockLedger
 * @covers \App\Market\Inventario\Infrastructure\Persistence\CatalogoStockProducts
 * @covers \App\Market\Inventario\Infrastructure\Http\StockController
 */
final class StockFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): StockController
    {
        return self::getContainer()->get(StockController::class);
    }

    /** @return array<mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    private function mainWarehouseId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
    }

    private function product(string $sku = 'arroz-stock'): int
    {
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, total_weight_base, created_at, updated_at)
            SELECT :sku, id, 'draft', 'Gift Cards', 'Retail', :texts, 1, 1, 'kg', 'weight', 'kg', 1, NOW(), NOW() FROM market_seller WHERE is_platform
        SQL, ['sku' => $sku, 'texts' => json_encode(['es' => ['name' => 'Arroz 1 kg']])]);

        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_product WHERE sku = :s', ['s' => $sku]);
    }

    private function adjust(int $warehouse, int $item, int $delta, string $reason = 'count', string $comment = 'Conteo del lunes', ?string $reference = null): Response
    {
        return $this->controller()->adjust(new AdjustStockDto($warehouse, 'product', $item, $delta, $reason, $comment, $reference));
    }

    public function testItCreatesTheLineAndRecordsTheMovementWithWhoDidIt(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $p = $this->product();

        $response = $this->adjust($w, $p, 10, 'initial', 'Existencia inicial', 'ALB-1');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->body($response);
        $this->assertSame([10, 0, 0, 10], [$body['onHand'], $body['reserved'], $body['safety'], $body['available']]);
        $this->assertSame('Arroz 1 kg', $body['product']['name']);
        $this->em->clear();
        $movement = self::getContainer()->get(StockLedger::class)->movements($w, $p, 10)[0];
        $this->assertSame(10, $movement->quantity());
        $this->assertSame(10, $movement->onHandAfter());
        $this->assertSame('initial', $movement->reason()->value);
        $this->assertSame('ALB-1', $movement->reference());
        $this->assertNotSame('sistema', $movement->actorName(), 'queda el usuario que hizo el ajuste');
        $this->assertGreaterThan(0, $movement->ownerCostCenterId());
        $this->assertGreaterThan(0, $movement->custodianCostCenterId());
    }

    public function testAnAdjustmentCannotLeaveTheStockBelowWhatIsReserved(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $p = $this->product();
        $this->adjust($w, $p, 10);
        $this->em->getConnection()->executeStatement('UPDATE market_stock_line SET reserved = 8 WHERE warehouse_id = :w AND item_id = :p', ['w' => $w, 'p' => $p]);
        $this->em->clear();

        $response = $this->adjust($w, $p, -5, 'loss', 'Se perdieron');

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('STOCK_BELOW_RESERVED', $this->body($response)['error']['code']);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_stock_movement'));
        $this->assertSame(10, (int) $this->em->getConnection()->fetchOne('SELECT on_hand FROM market_stock_line'));
    }

    public function testTheCommentIsMandatory(): void
    {
        $this->admin();

        $response = $this->adjust($this->mainWarehouseId(), $this->product(), 5, 'count', '  ');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_STOCK', $this->body($response)['error']['code']);
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_stock_movement'));
    }

    public function testADraftWarehouseAdmitsNoStock(): void
    {
        $this->admin();
        $draft = $this->body(self::getContainer()->get(WarehouseController::class)->create(new CreateWarehouseDto('Borrador stock')))['id'];

        $response = $this->adjust($draft, $this->product(), 5);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('STOCK_WAREHOUSE_INVALID', $this->body($response)['error']['code']);
    }

    public function testADigitalProductHasNoStock(): void
    {
        $this->admin();
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, value_currency, value_min_minor, value_max_minor, created_at, updated_at)
            SELECT 'giftcard-stock', id, 'draft', 'Gift Cards', 'Retail', '{"es":{"name":"Tarjeta"}}', 'USD', 2500, NULL, NOW(), NOW() FROM market_seller WHERE is_platform
        SQL);
        $id = (int) $this->em->getConnection()->fetchOne("SELECT id FROM market_product WHERE sku = 'giftcard-stock'");

        $response = $this->adjust($this->mainWarehouseId(), $id, 5);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('STOCK_PRODUCT_INVALID', $this->body($response)['error']['code']);
    }

    public function testTheSafetyStockIsSetAndReducesWhatIsAvailable(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $p = $this->product();
        $this->adjust($w, $p, 10);

        $response = $this->controller()->setSafety(new SetSafetyStockDto($w, 'product', $p, 3));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame([10, 3, 7], [$this->body($response)['onHand'], $this->body($response)['safety'], $this->body($response)['available']]);
        $missing = $this->controller()->setSafety(new SetSafetyStockDto($w, 'product', $this->product('otro-stock'), 3));
        $this->assertSame(Response::HTTP_NOT_FOUND, $missing->getStatusCode());
    }

    public function testALineSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $p = $this->product();
        $this->adjust($w, $p, 7);
        $this->em->clear();

        $line = self::getContainer()->get(StockLedger::class)->find($w, StockItemType::Product, $p);

        $this->assertSame(7, $line?->onHand());
    }
}
