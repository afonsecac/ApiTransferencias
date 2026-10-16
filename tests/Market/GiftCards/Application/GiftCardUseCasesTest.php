<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Application;

use App\Market\GiftCards\Application\CancelGiftCard;
use App\Market\GiftCards\Application\GetGiftCard;
use App\Market\GiftCards\Application\IssueGiftCardBatch;
use App\Market\GiftCards\Application\IssuePlan;
use App\Market\GiftCards\Application\ListGiftCards;
use App\Market\GiftCards\Application\ListRedemptions;
use App\Market\GiftCards\Application\MarkGiftCardSold;
use App\Market\GiftCards\Application\RedeemGiftCard;
use App\Market\GiftCards\Application\RevealGiftCardPin;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardAlreadyRedeemed;
use App\Market\GiftCards\Domain\GiftCardCostCenter;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\GiftCardProductInvalid;
use App\Market\GiftCards\Domain\GiftCardStatus;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;
use PHPUnit\Framework\TestCase;

final class GiftCardUseCasesTest extends TestCase
{
    private InMemoryGiftCardRepository $repo;
    private FakeGiftCardVault $vault;
    private FakeGiftCardProducts $products;
    private GiftCardActor $actor;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->repo = new InMemoryGiftCardRepository();
        $this->vault = new FakeGiftCardVault();
        $this->products = new FakeGiftCardProducts();
        $centers = [new GiftCardCostCenter(7, 'PRINCIPAL', 'Principal', true, true), new GiftCardCostCenter(8, 'OLD', 'Antiguo', false, false), new GiftCardCostCenter(9, 'ALT', 'Alterno', false, true)];
        $this->products->add(new GiftCardProduct(40, 'Tarjeta de compra 25', true, true, 'USD', 2500, null, $centers));
        $this->products->add(new GiftCardProduct(41, 'Tarjeta variable', true, false, 'USD', 1000, 10000, $centers));
        $this->products->add(new GiftCardProduct(42, 'Arroz', false, false, '', 0, null, $centers));
        $this->actor = new GiftCardActor(9, 'm.perez');
        $this->now = new \DateTimeImmutable('2026-10-16 12:00:00');
    }

    private function issue(int $product = 40, ?IssuePlan $plan = null, ?string $expires = '2027-12-31', ?int $center = null): \App\Market\GiftCards\Application\IssuedBatch
    {
        return (new IssueGiftCardBatch($this->repo, $this->products, $this->vault))($product, $plan ?? IssuePlan::range(null, null, null, 3), $expires, $center, $this->actor, $this->now);
    }

    public function testItIssuesALotWithConsecutiveSerialsAndDifferentPins(): void
    {
        $issued = $this->issue();

        $this->assertCount(3, $issued->cards);
        $this->assertSame(['GC-000001', 'GC-000002', 'GC-000003'], array_map(static fn ($c) => $c->serial(), $issued->cards));
        $this->assertCount(3, array_unique(array_map(static fn ($c) => $c->pinHash(), $issued->cards)));
        $this->assertSame(2500, $issued->cards[0]->valueMinor());
        $this->assertSame(7, $issued->cards[0]->costCenterId(), 'por defecto, el centro principal del vendedor');
        $this->assertSame(3, $issued->batch->quantity());
    }

    public function testThePinIsNeverStoredInTheClear(): void
    {
        $card = $this->issue(plan: IssuePlan::single(null))->cards[0];

        $this->assertStringNotContainsString('AAAA-BBBB', $card->pinHash());
        $this->assertSame('AAAA-BBBB-CCCC-0001', $this->vault->decrypt($card->pinCipher()));
    }

    public function testEveryCardOfALotLeavesAnIssuedEvent(): void
    {
        $this->issue(plan: IssuePlan::range(null, null, null, 2));

        $this->assertCount(2, array_filter($this->repo->events, static fn ($e) => $e->type() === 'issued'));
        $this->assertSame('m.perez', $this->repo->events[0]->actorName());
    }

    public function testAnIndividualCardOfARangedProductTakesItsOwnValueInsideTheRange(): void
    {
        $this->assertSame(5000, $this->issue(41, IssuePlan::single('50.00'))->cards[0]->valueMinor());
        $this->assertCount(1, $this->issue(41, IssuePlan::single('50.00'))->cards);

        foreach ([null, '5.00', '150.00', 'abc'] as $value) {
            try {
                $this->issue(41, IssuePlan::single($value));
                $this->fail('debía rechazar el valor ' . var_export($value, true));
            } catch (InvalidGiftCard) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnIndividualCardOfAFixedProductTakesTheProductsValue(): void
    {
        $this->assertSame(2500, $this->issue(40, IssuePlan::single(null))->cards[0]->valueMinor());
    }

    public function testAFixedProductIgnoresAnyOtherValue(): void
    {
        $this->expectException(InvalidGiftCard::class);
        $this->issue(40, IssuePlan::single('30.00'));
    }

    public function testABatchGoesFromMinToMaxInStepsOneCardPerValue(): void
    {
        $issued = $this->issue(41, IssuePlan::range('10.00', '100.00', '10.00', 1));

        $this->assertSame([1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000], array_map(static fn ($c) => $c->valueMinor(), $issued->cards));
        $this->assertSame(10, $issued->batch->quantity());
        $this->assertSame([1000, 10000, 1000], [$issued->batch->valueMinMinor(), $issued->batch->valueMaxMinor(), $issued->batch->stepMinor()]);
    }

    public function testABatchCanIssueSeveralCardsOfEachValue(): void
    {
        $issued = $this->issue(41, IssuePlan::range('10.00', '30.00', '10.00', 2));

        $this->assertSame([1000, 1000, 2000, 2000, 3000, 3000], array_map(static fn ($c) => $c->valueMinor(), $issued->cards));
        $this->assertSame(6, $issued->batch->quantity());
    }

    public function testTheStepCanHaveCents(): void
    {
        $issued = $this->issue(41, IssuePlan::range('10.00', '11.00', '0.50', 1));

        $this->assertSame([1000, 1050, 1100], array_map(static fn ($c) => $c->valueMinor(), $issued->cards));
    }

    public function testTheLastValueIsNotPastTheMaximum(): void
    {
        $issued = $this->issue(41, IssuePlan::range('10.00', '35.00', '10.00', 1));

        $this->assertSame([1000, 2000, 3000], array_map(static fn ($c) => $c->valueMinor(), $issued->cards));
    }

    public function testMinEqualToMaxIsOneValueAndNeedsNoStep(): void
    {
        $issued = $this->issue(41, IssuePlan::range('50.00', '50.00', null, 4));

        $this->assertSame([5000, 5000, 5000, 5000], array_map(static fn ($c) => $c->valueMinor(), $issued->cards));
    }

    public function testABatchOfAFixedProductIssuesThatValueOnlyAndDoesNotAcceptARange(): void
    {
        $this->assertSame([2500, 2500], array_map(static fn ($c) => $c->valueMinor(), $this->issue(40, IssuePlan::range(null, null, null, 2))->cards));

        $this->expectException(InvalidGiftCard::class);
        $this->issue(40, IssuePlan::range('10.00', '30.00', '10.00', 1));
    }

    public function testABadRangeIsRejectedAndNothingIsStored(): void
    {
        $bad = [
            'sin mínimo' => IssuePlan::range(null, '100.00', '10.00', 1),
            'sin máximo' => IssuePlan::range('10.00', null, '10.00', 1),
            'mínimo mayor que máximo' => IssuePlan::range('100.00', '10.00', '10.00', 1),
            'sin paso' => IssuePlan::range('10.00', '100.00', null, 1),
            'paso cero' => IssuePlan::range('10.00', '100.00', '0', 1),
            'fuera por debajo' => IssuePlan::range('5.00', '50.00', '5.00', 1),
            'fuera por encima' => IssuePlan::range('10.00', '150.00', '10.00', 1),
            'valor no numérico' => IssuePlan::range('diez', '100.00', '10.00', 1),
            'cero por valor' => IssuePlan::range('10.00', '100.00', '10.00', 0),
            'demasiadas tarjetas' => IssuePlan::range('10.00', '100.00', '0.01', 1),
            'por valor excesivo' => IssuePlan::range('10.00', '100.00', '10.00', 101),
        ];
        foreach ($bad as $name => $plan) {
            try {
                $this->issue(41, $plan);
                $this->fail('debía rechazar: ' . $name);
            } catch (InvalidGiftCard) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->repo->cards);
    }

    public function testOnlyADigitalProductWithAValueCanBeIssued(): void
    {
        $this->expectException(GiftCardProductInvalid::class);
        $this->issue(42);
    }

    public function testAnUnknownProductIsRejected(): void
    {
        $this->expectException(GiftCardProductInvalid::class);
        $this->issue(99);
    }

    public function testTheQuantityAndTheExpiryHaveLimits(): void
    {
        foreach ([[0, '2027-12-31'], [1001, '2027-12-31'], [1, '2026-10-15'], [1, 'no-es-fecha']] as [$quantity, $expires]) {
            try {
                $this->issue(40, IssuePlan::range(null, null, null, $quantity), $expires);
                $this->fail('debía rechazar ' . $quantity . '/' . $expires);
            } catch (InvalidGiftCard) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->repo->cards);
    }

    public function testTheCostCenterMustBeAnActiveOneOfTheSeller(): void
    {
        $this->assertSame(9, $this->issue(center: 9)->cards[0]->costCenterId());

        foreach ([8, 99] as $center) {
            try {
                $this->issue(center: $center);
                $this->fail('debía rechazar el centro ' . $center);
            } catch (InvalidGiftCard) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNoExpiryIsAllowed(): void
    {
        $this->assertNull($this->issue(expires: null)->cards[0]->expiresAt());
    }

    public function testTheListFiltersBySerialAndStatusAndShowsEffectiveStatus(): void
    {
        $this->issue();
        (new MarkGiftCardSold($this->repo))('GC-000001', 'AWS Malta', 'venta-1', $this->actor, $this->now);
        $list = new ListGiftCards($this->repo, $this->products);

        $this->assertCount(3, $list(null, null, null, null, $this->now));
        $this->assertSame(['GC-000001'], array_map(static fn ($r) => $r->card->serial(), $list(null, 'active', null, null, $this->now)));
        $this->assertSame(['GC-000002'], array_map(static fn ($r) => $r->card->serial(), $list(null, null, null, '000002', $this->now)));
        $this->assertCount(3, $list(null, 'expired', null, null, new \DateTimeImmutable('2028-06-01')));
    }

    public function testTheDetailCarriesTheTraceInOrder(): void
    {
        $this->issue(plan: IssuePlan::single(null));
        (new MarkGiftCardSold($this->repo))('GC-000001', 'AWS Malta', 'venta-1', $this->actor, $this->now);

        $detail = (new GetGiftCard($this->repo, $this->products))('GC-000001', $this->now);

        $this->assertSame(['issued', 'sold'], array_map(static fn ($e) => $e->type(), $detail->events));
        $this->assertSame('Tarjeta de compra 25', $detail->product?->name);
        $this->assertSame(GiftCardStatus::Active, $detail->status);
    }

    public function testAnUnknownCardIsNotFound(): void
    {
        $this->expectException(GiftCardNotFound::class);
        (new GetGiftCard($this->repo, $this->products))('GC-999999', $this->now);
    }

    public function testRevealingThePinReturnsItAndLeavesWhoAndWhen(): void
    {
        $this->issue(plan: IssuePlan::single(null));

        $pin = (new RevealGiftCardPin($this->repo, $this->vault))('GC-000001', new GiftCardActor(3, 'otra.persona'), $this->now);

        $this->assertSame('AAAA-BBBB-CCCC-0001', $pin);
        $reveal = array_values(array_filter($this->repo->events, static fn ($e) => $e->type() === 'pin_revealed'))[0];
        $this->assertSame('otra.persona', $reveal->actorName());
    }

    public function testASaleActivatesTheCardAndAnActiveCardCanBeRedeemedByItsPin(): void
    {
        $this->issue(plan: IssuePlan::single(null));
        (new MarkGiftCardSold($this->repo))('GC-000001', 'AWS Malta', 'venta-1', $this->actor, $this->now);

        $result = (new RedeemGiftCard($this->repo, $this->vault))('aaaa-bbbb-cccc-0001', 'canje-1', $this->actor, $this->now);

        $this->assertFalse($result->replay);
        $this->assertSame(GiftCardStatus::Redeemed, $result->card->status());
        $this->assertContains('redeemed', array_map(static fn ($e) => $e->type(), $this->repo->events));
    }

    public function testRedeemingTwiceWithTheSameKeyIsAReplayAndWithAnotherKeyIsRejected(): void
    {
        $this->issue(plan: IssuePlan::single(null));
        (new MarkGiftCardSold($this->repo))('GC-000001', 'AWS Malta', 'venta-1', $this->actor, $this->now);
        $redeem = new RedeemGiftCard($this->repo, $this->vault);
        $redeem('AAAA-BBBB-CCCC-0001', 'canje-1', $this->actor, $this->now);
        $before = count($this->repo->events);

        $replay = $redeem('AAAA-BBBB-CCCC-0001', 'canje-1', $this->actor, $this->now);
        $this->assertTrue($replay->replay);
        $this->assertCount($before, $this->repo->events, 'una repetición no deja otro evento');

        $this->expectException(GiftCardAlreadyRedeemed::class);
        $redeem('AAAA-BBBB-CCCC-0001', 'canje-2', $this->actor, $this->now);
    }

    public function testAWrongPinIsNotFoundAndANotSoldCardCannotBeRedeemed(): void
    {
        $this->issue(plan: IssuePlan::single(null));
        $redeem = new RedeemGiftCard($this->repo, $this->vault);

        try {
            $redeem('XXXX-XXXX-XXXX-XXXX', 'canje-1', $this->actor, $this->now);
            $this->fail('debía fallar');
        } catch (GiftCardNotFound) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(GiftCardTransitionInvalid::class);
        $redeem('AAAA-BBBB-CCCC-0001', 'canje-1', $this->actor, $this->now);
    }

    public function testTheRedemptionNeedsAKey(): void
    {
        $this->expectException(InvalidGiftCard::class);
        (new RedeemGiftCard($this->repo, $this->vault))('AAAA-BBBB-CCCC-0001', '  ', $this->actor, $this->now);
    }

    public function testCancellingLeavesAReasonAndATraceEvent(): void
    {
        $this->issue(plan: IssuePlan::single(null));

        $card = (new CancelGiftCard($this->repo))('GC-000001', 'Emitida por error', $this->actor, $this->now);

        $this->assertSame(GiftCardStatus::Cancelled, $card->status());
        $this->assertContains('cancelled', array_map(static fn ($e) => $e->type(), $this->repo->events));
    }

    public function testTheRedemptionsListIsNewestFirst(): void
    {
        $this->issue(plan: IssuePlan::range(null, null, null, 2));
        $sell = new MarkGiftCardSold($this->repo);
        $sell('GC-000001', 'A', 'v1', $this->actor, $this->now);
        $sell('GC-000002', 'B', 'v2', $this->actor, $this->now);
        $redeem = new RedeemGiftCard($this->repo, $this->vault);
        $redeem('AAAA-BBBB-CCCC-0001', 'c1', $this->actor, $this->now);
        $redeem('AAAA-BBBB-CCCC-0002', 'c2', $this->actor, $this->now->modify('+1 hour'));

        $list = (new ListRedemptions($this->repo, $this->products))(50);

        $this->assertSame(['GC-000002', 'GC-000001'], array_map(static fn ($r) => $r->card->serial(), $list));
    }
}
