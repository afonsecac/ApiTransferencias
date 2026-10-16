<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Infrastructure;

use App\Market\GiftCards\Application\MarkGiftCardSold;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Infrastructure\Http\CancelGiftCardDto;
use App\Market\GiftCards\Infrastructure\Http\GiftCardController;
use App\Market\GiftCards\Infrastructure\Http\IssueGiftCardBatchDto;
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

    private function issue(int $product, int $quantity = 2, ?string $value = null, ?string $expires = '2099-12-31'): Response
    {
        return $this->controller()->issue(new IssueGiftCardBatchDto($product, $quantity, $value, $expires, null));
    }

    private function sell(string $serial): void
    {
        (new MarkGiftCardSold(self::getContainer()->get(GiftCardRepository::class)))($serial, 'AWS Malta', 'venta-1', new GiftCardActor(1, 'sistema'));
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
        $this->assertMatchesRegularExpression('/^GC-\d{6}$/', $body['serials'][0]);
        $this->assertMatchesRegularExpression('/^L-\d{4}-\d{2}-\d{4}$/', $body['batch']['code']);
        $row = $this->em->getConnection()->fetchAssociative('SELECT pin_cipher, pin_hash, status, value_minor, currency FROM market_gift_card ORDER BY id LIMIT 1');
        $this->assertStringStartsWith('v1:', $row['pin_cipher'], 'el PIN se guarda cifrado');
        $this->assertSame(64, strlen($row['pin_hash']));
        $this->assertSame(['issued', 2500, 'USD'], [$row['status'], (int) $row['value_minor'], $row['currency']]);
    }

    public function testThePinIsRevealedOnlyOnRequestAndLeavesATrace(): void
    {
        $this->issue($this->digitalProduct(), 1);
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');

        $list = (string) $this->controller()->list(new Request())->getContent();
        $this->assertStringNotContainsString('pin', strtolower($list));

        $pin = $this->body($this->controller()->pin($serial))['pin'];
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/', $pin);
        $events = array_column($this->body($this->controller()->get($serial))['events'], 'type');
        $this->assertSame(['issued', 'pin_revealed'], $events);
    }

    public function testASoldCardIsRedeemedByItsPinAndRepeatingTheKeyIsAReplay(): void
    {
        $this->issue($this->digitalProduct(), 1);
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');
        $pin = $this->body($this->controller()->pin($serial))['pin'];
        $this->sell($serial);

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
        $this->assertSame(['issued', 'pin_revealed', 'sold', 'redeemed'], $types, 'la repetición no deja otro hecho');
    }

    public function testACardThatWasNotSoldCannotBeRedeemed(): void
    {
        $this->issue($this->digitalProduct(), 1);
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');
        $pin = $this->body($this->controller()->pin($serial))['pin'];

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
        $this->issue($this->digitalProduct(), 1);
        $this->em->getConnection()->executeStatement("UPDATE market_gift_card SET expires_at = '2020-01-01'");
        $this->em->clear();

        $rows = $this->body($this->controller()->list(new Request(query: ['status' => 'expired'])));

        $this->assertCount(1, $rows);
        $this->assertSame('expired', $rows[0]['status']);
    }

    public function testCancellingNeedsAReasonAndLeavesATrace(): void
    {
        $this->issue($this->digitalProduct(), 1);
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

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->issue($product, 1)->getStatusCode());
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->issue($product, 1, '150.00')->getStatusCode());
        $ok = $this->issue($product, 1, '50.00');
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

        $response = $this->issue($id, 1);

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
        $this->issue($this->digitalProduct(), 1);
        $serial = (string) $this->em->getConnection()->fetchOne('SELECT serial FROM market_gift_card LIMIT 1');
        $pin = $this->body($this->controller()->pin($serial))['pin'];
        $this->sell($serial);
        $this->controller()->redeem(new RedeemGiftCardDto($pin, 'canje-1'));

        $rows = $this->body($this->controller()->redemptions());

        $this->assertSame([$serial], array_column($rows, 'serial'));
        $this->assertSame('canje-1', $rows[0]['redemptionRef']);
    }
}
