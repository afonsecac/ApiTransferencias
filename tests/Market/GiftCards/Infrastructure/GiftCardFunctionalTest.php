<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Infrastructure;

use App\Market\GiftCards\Application\MarkGiftCardSold;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Infrastructure\Http\CancelGiftCardDto;
use App\Market\GiftCards\Infrastructure\Http\GiftCardController;
use App\Market\GiftCards\Infrastructure\Http\IssueGiftCardBatchDto;
use App\Market\GiftCards\Infrastructure\Http\IssueGiftCardDto;
use App\Market\GiftCards\Infrastructure\Http\RedeemGiftCardDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-276 contra Postgres real: emisión de lotes, PIN cifrado, canje idempotente, anulación y trazabilidad.
 *
 * @covers \App\Market\GiftCards\Infrastructure\Persistence\DoctrineGiftCardRepository
 * @covers \App\Market\GiftCards\Infrastructure\Persistence\SecretCipherPinVault
 * @covers \App\Market\GiftCards\Infrastructure\Persistence\CatalogoOrganizacionGiftCardProducts
 * @covers \App\Market\GiftCards\Infrastructure\Http\GiftCardController
 */
final class GiftCardFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): GiftCardController
    {
        return self::getContainer()->get(GiftCardController::class);
    }

    /** @return array<mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function digitalProduct(string $sku = 'tarjeta-25', int $min = 2500, ?int $max = null): int
    {
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, value_currency, value_min_minor, value_max_minor, created_at, updated_at)
            SELECT :sku, id, 'draft', 'Gift Cards', 'Retail', '{"es":{"name":"Tarjeta de compra"}}', 'USD', :min, :max, NOW(), NOW() FROM market_seller WHERE is_platform
        SQL, ['sku' => $sku, 'min' => $min, 'max' => $max]);

        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_product WHERE sku = :s', ['s' => $sku]);
    }

    private function issue(int $product, int $perValue = 2, ?string $min = null, ?string $max = null, ?string $step = null, ?string $expires = '2099-12-31'): Response
    {
        return $this->controller()->issue(new IssueGiftCardBatchDto($product, $min, $max, $step, $perValue, $expires, null));
    }

    private function issueSingle(int $product, ?string $value = null): Response
    {
        return $this->controller()->issueSingle(new IssueGiftCardDto($product, $value, '2099-12-31', null));
    }

    /** La venta es el único camino por el que sale el PIN: se le entrega al comprador. */
    private function sell(string $serial, string $saleRef = 'venta-1'): string
    {
        $container = self::getContainer();

        return (new MarkGiftCardSold($container->get(GiftCardRepository::class), $container->get(GiftCardPinVault::class)))($serial, 'AWS Malta', $saleRef, new GiftCardActor(1, 'sistema'))->pin;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    public function testItIssuesALotWithConsecutiveSerialsAndAnEncryptedPin(): void
    {
        $response = $this->issue($this->digitalProduct(), 2);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->body($response);
        $this->assertCount(2, $body['serials']);
        $this->assertMatchesRegularExpression('/^9\d{15}$/', $body['serials'][0]);
        $this->assertTrue(\App\Market\GiftCards\Domain\GiftCardNumber::passesLuhn($body['serials'][0]));
        $this->assertMatchesRegularExpression('/^L-\d{4}-\d{2}-\d{4}$/', $body['batch']['code']);
        $row = $this->em->getConnection()->fetchAssociative('SELECT pin_cipher, pin_hash, status, value_minor, currency FROM market_gift_card ORDER BY id LIMIT 1');
        $this->assertStringStartsWith('v1:', $row['pin_cipher'], 'el PIN se guarda cifrado');
        $this->assertSame(64, strlen($row['pin_hash']));
        $this->assertSame(['issued', 2500, 'USD'], [$row['status'], (int) $row['value_minor'], $row['currency']]);
    }

    public function testThePinNeverComesOutOfTheAdministration(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $payloads = [(string) $this->controller()->list(new Request())->getContent(), (string) $this->controller()->get($serial)->getContent(), (string) $this->controller()->redemptions()->getContent()];
        foreach ($payloads as $payload) {
            $this->assertStringNotContainsString('"pin', strtolower($payload));
            $this->assertStringNotContainsString('pin_cipher', strtolower($payload));
        }
        $routes = array_map(static fn ($r) => $r->getPath(), array_values(self::getContainer()->get('router')->getRouteCollection()->all()));
        $this->assertEmpty(array_filter($routes, static fn (string $path) => str_contains($path, '/gift-cards') && str_ends_with($path, '/pin')), 'no hay endpoint para ver el PIN');
    }

    public function testASoldCardIsRedeemedByThePinTheBuyerWasGivenAndRepeatingTheKeyIsAReplay(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');
        $pin = $this->sell($serial);

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/', $pin);
        $first = $this->controller()->redeem(new RedeemGiftCardDto(strtolower($pin), 'canje-1'));
        $this->assertSame(Response::HTTP_OK, $first->getStatusCode(), (string) $first->getContent());
        $this->assertFalse($this->body($first)['replay']);
        $this->assertSame('redeemed', $this->body($first)['card']['status']);

        $replay = $this->controller()->redeem(new RedeemGiftCardDto($pin, 'canje-1'));
        $this->assertTrue($this->body($replay)['replay']);

        $other = $this->controller()->redeem(new RedeemGiftCardDto($pin, 'canje-2'));
        $this->assertSame(Response::HTTP_CONFLICT, $other->getStatusCode());
        $this->assertSame('GIFT_CARD_ALREADY_REDEEMED', $this->body($other)['error']['code']);
        $types = array_column($this->body($this->controller()->get($serial))['events'], 'type');
        $this->assertSame(['issued', 'sold', 'redeemed'], $types, 'la repetición no deja otro hecho');
    }

    public function testRepeatingTheSameSaleDeliversTheSamePin(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $this->assertSame($this->sell($serial), $this->sell($serial));
        $this->assertSame(['issued', 'sold'], array_column($this->body($this->controller()->get($serial))['events'], 'type'));
    }

    public function testTheDetailCarriesTheCostCenterCodeAndTheBatchCode(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $detail = $this->body($this->controller()->get($serial));

        $this->assertNotEmpty($detail['costCenterCode']);
        $this->assertMatchesRegularExpression('/^L-\d{4}-\d{2}-\d{4}$/', $detail['batchCode']);
    }

    public function testTheCardCanBeFoundWithItsNumberTypedLikeACreditCard(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $spaced = trim(chunk_split($serial, 4, ' '));
        $rows = $this->body($this->controller()->list(new Request(query: ['serial' => $spaced])));

        $this->assertSame([$serial], array_column($rows, 'serial'));
    }

    public function testACardThatWasNotSoldCannotBeRedeemed(): void
    {
        $this->issueSingle($this->digitalProduct());
        $card = $this->em->getConnection()->fetchAssociative('SELECT id, serial FROM market_gift_card LIMIT 1');
        // Sin venta nadie conoce su PIN: se prueba con uno cualquiera y con la huella del PIN real vía la venta de otra tarjeta.
        $this->issueSingle($this->digitalProduct('otra-tarjeta'));
        $other = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card WHERE id <> :id LIMIT 1', ['id' => $card['id']]);
        $pin = $this->sell($other);
        $this->em->getConnection()->executeStatement("UPDATE market_gift_card SET status = 'issued', sold_to = NULL, sale_ref = NULL WHERE serial = :s", ['s' => $other]);
        $this->em->clear();

        $response = $this->controller()->redeem(new RedeemGiftCardDto($pin, 'canje-1'));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('GIFT_CARD_TRANSITION_INVALID', $this->body($response)['error']['code']);
    }

    public function testAWrongPinAnswers404(): void
    {
        $response = $this->controller()->redeem(new RedeemGiftCardDto('ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'canje-1'));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAnExpiredCardShowsAsExpired(): void
    {
        $this->issueSingle($this->digitalProduct());
        $this->em->getConnection()->executeStatement("UPDATE market_gift_card SET expires_at = '2020-01-01'");
        $this->em->clear();

        $rows = $this->body($this->controller()->list(new Request(query: ['status' => 'expired'])));

        $this->assertCount(1, $rows);
        $this->assertSame('expired', $rows[0]['status']);
    }

    public function testCancellingNeedsAReasonAndLeavesATrace(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $response = $this->controller()->cancel($serial, new CancelGiftCardDto('Emitida por error'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('cancelled', $this->body($response)['status']);
        $this->assertSame('Emitida por error', $this->body($response)['cancelReason']);
        $this->assertContains('cancelled', array_column($this->body($response)['events'], 'type'));
    }

    public function testARangedProductNeedsAValueInsideItsRange(): void
    {
        $product = $this->digitalProduct('tarjeta-variable', 1000, 10000);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->issueSingle($product)->getStatusCode());
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->issueSingle($product, '150.00')->getStatusCode());
        $ok = $this->issueSingle($product, '50.00');
        $this->assertSame(Response::HTTP_CREATED, $ok->getStatusCode(), (string) $ok->getContent());
        $this->assertSame(5000, (int) $this->em->getConnection()->fetchOne('SELECT value_minor FROM market_gift_card'));
    }

    public function testAPhysicalProductCannotBeIssued(): void
    {
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, total_weight_base, created_at, updated_at)
            SELECT 'arroz-gc', id, 'draft', 'Gift Cards', 'Retail', '{"es":{"name":"Arroz"}}', 1, 1, 'kg', 'weight', 'kg', 1, NOW(), NOW() FROM market_seller WHERE is_platform
        SQL);
        $id = (int) $this->em->getConnection()->fetchOne("SELECT id FROM market_product WHERE sku = 'arroz-gc'");

        $response = $this->issueSingle($id);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('GIFT_CARD_PRODUCT_INVALID', $this->body($response)['error']['code']);
    }

    public function testTheProductEndpointOffersTheValueAndTheSellersCostCenters(): void
    {
        $body = $this->body($this->controller()->product($this->digitalProduct()));

        $this->assertTrue($body['fixed']);
        $this->assertSame([2500, 'USD'], [$body['minMinor'], $body['currency']]);
        $this->assertNotEmpty($body['costCenters']);
    }

    public function testTheRedemptionsListShowsTheLatest(): void
    {
        $this->issueSingle($this->digitalProduct());
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');
        $pin = $this->sell($serial);
        $this->controller()->redeem(new RedeemGiftCardDto($pin, 'canje-1'));

        $rows = $this->body($this->controller()->redemptions());

        $this->assertSame([$serial], array_column($rows, 'serial'));
        $this->assertSame('canje-1', $rows[0]['redemptionRef']);
    }

    public function testABatchByRangeIssuesOneCardPerValueAndRemembersItsRange(): void
    {
        $product = $this->digitalProduct('tarjeta-rango', 1000, 10000);

        $response = $this->issue($product, 1, '10.00', '50.00', '10.00');

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(5, $this->body($response)['batch']['quantity']);
        $this->assertSame(['1000', '2000', '3000', '4000', '5000'], $this->em->getConnection()->fetchFirstColumn('SELECT value_minor::text FROM market_gift_card ORDER BY id'));
        $this->assertSame(['1000', '5000', '1000'], array_values($this->em->getConnection()->fetchNumeric('SELECT value_min_minor::text, value_max_minor::text, step_minor::text FROM market_gift_card_batch')));
    }

    public function testABadRangeAnswers422AndStoresNothing(): void
    {
        $product = $this->digitalProduct('tarjeta-rango-mal', 1000, 10000);

        $response = $this->issue($product, 1, '10.00', '50.00', '0');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_GIFT_CARD', $this->body($response)['error']['code']);
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_gift_card'));
    }

    public function testABatchOfAFixedProductIssuesThatValue(): void
    {
        $response = $this->issue($this->digitalProduct(), 3);

        $this->assertSame(3, $this->body($response)['batch']['quantity']);
        $this->assertSame(['2500'], array_unique($this->em->getConnection()->fetchFirstColumn('SELECT value_minor::text FROM market_gift_card')));
    }
}
