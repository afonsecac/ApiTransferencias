<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Domain;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardAlreadyRedeemed;
use App\Market\GiftCards\Domain\GiftCardStatus;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;
use PHPUnit\Framework\TestCase;

final class GiftCardTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-16 12:00:00');
    }

    private function card(?string $expires = '2027-12-31'): GiftCard
    {
        return GiftCard::issue(1, 40, 'GC-000123', 2500, 'USD', 'cipher', 'hash', $expires === null ? null : new \DateTimeImmutable($expires), 7, $this->now);
    }

    public function testItIsBornIssuedWithItsValueSerialAndCostCenter(): void
    {
        $card = $this->card();

        $this->assertSame(GiftCardStatus::Issued, $card->status());
        $this->assertSame(['GC-000123', 2500, 'USD', 7], [$card->serial(), $card->valueMinor(), $card->currency(), $card->costCenterId()]);
        $this->assertNull($card->soldTo());
    }

    public function testItRejectsAnInvalidSerialOrValue(): void
    {
        $this->expectException(InvalidGiftCard::class);
        GiftCard::issue(1, 40, 'mal', 2500, 'USD', 'c', 'h', null, 7, $this->now);
    }

    public function testItRejectsAZeroValue(): void
    {
        $this->expectException(InvalidGiftCard::class);
        GiftCard::issue(1, 40, 'GC-000123', 0, 'USD', 'c', 'h', null, 7, $this->now);
    }

    public function testASaleActivatesItAndRecordsToWhomAndWhich(): void
    {
        $card = $this->card();
        $card->markSold('AWS Malta', 'venta-48213', $this->now);

        $this->assertSame(GiftCardStatus::Active, $card->status());
        $this->assertSame(['AWS Malta', 'venta-48213'], [$card->soldTo(), $card->saleRef()]);
    }

    public function testItCannotBeSoldTwice(): void
    {
        $card = $this->card();
        $card->markSold('A', 'v1', $this->now);

        $this->expectException(GiftCardTransitionInvalid::class);
        $card->markSold('B', 'v2', $this->now);
    }

    public function testAnActiveCardIsRedeemedOnce(): void
    {
        $card = $this->card();
        $card->markSold('A', 'v1', $this->now);

        $this->assertTrue($card->redeem('canje-1', $this->now));
        $this->assertSame(GiftCardStatus::Redeemed, $card->status());
        $this->assertSame('canje-1', $card->redemptionRef());
    }

    public function testRepeatingTheSameRedemptionIsAHarmlessReplay(): void
    {
        $card = $this->card();
        $card->markSold('A', 'v1', $this->now);
        $card->redeem('canje-1', $this->now);

        $this->assertFalse($card->redeem('canje-1', $this->now->modify('+1 hour')));
        $this->assertEquals($this->now, $card->redeemedAt());
    }

    public function testAnotherRedemptionOfARedeemedCardIsRejected(): void
    {
        $card = $this->card();
        $card->markSold('A', 'v1', $this->now);
        $card->redeem('canje-1', $this->now);

        $this->expectException(GiftCardAlreadyRedeemed::class);
        $card->redeem('canje-2', $this->now);
    }

    public function testACardThatWasNotSoldCannotBeRedeemed(): void
    {
        $this->expectException(GiftCardTransitionInvalid::class);
        $this->card()->redeem('canje-1', $this->now);
    }

    public function testAnExpiredCardShowsAsExpiredAndCannotBeSoldOrRedeemed(): void
    {
        $card = $this->card('2026-10-15');
        $this->assertSame(GiftCardStatus::Expired, $card->effectiveStatus($this->now));

        $this->expectException(GiftCardTransitionInvalid::class);
        $card->markSold('A', 'v1', $this->now);
    }

    public function testTheCardIsStillValidOnItsLastDay(): void
    {
        $card = $this->card('2026-10-16');

        $this->assertSame(GiftCardStatus::Issued, $card->effectiveStatus($this->now));
    }

    public function testARedeemedCardNeverExpires(): void
    {
        $card = $this->card('2026-10-20');
        $card->markSold('A', 'v1', $this->now);
        $card->redeem('canje-1', $this->now);

        $this->assertSame(GiftCardStatus::Redeemed, $card->effectiveStatus($this->now->modify('+2 years')));
    }

    public function testItIsCancelledWithAReasonOnlyBeforeBeingRedeemed(): void
    {
        $card = $this->card();
        $card->cancel('Emitida por error', $this->now);
        $this->assertSame(GiftCardStatus::Cancelled, $card->status());
        $this->assertSame('Emitida por error', $card->cancelReason());

        $sold = $this->card();
        $sold->markSold('A', 'v1', $this->now);
        $sold->redeem('c', $this->now);
        $this->expectException(GiftCardTransitionInvalid::class);
        $sold->cancel('tarde', $this->now);
    }

    public function testCancellingNeedsAReason(): void
    {
        $this->expectException(InvalidGiftCard::class);
        $this->card()->cancel('  ', $this->now);
    }

    public function testACancelledCardCannotBeSoldOrRedeemed(): void
    {
        $card = $this->card();
        $card->cancel('Anulada', $this->now);

        $this->expectException(GiftCardTransitionInvalid::class);
        $card->markSold('A', 'v1', $this->now);
    }
}
