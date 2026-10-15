<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\Relevance;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Domain\Relevance
 */
final class RelevanceTest extends TestCase
{
    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    public function testNoneIsLevelZeroAndNeverApplies(): void
    {
        $none = Relevance::none();

        $this->assertSame(0, $none->level());
        $this->assertNull($none->from());
        $this->assertNull($none->until());
        $this->assertFalse($none->isActiveOn($this->day('2026-10-15')));
    }

    public function testItAppliesInsideItsDatesInclusive(): void
    {
        $relevance = Relevance::of(2, $this->day('2026-10-12'), $this->day('2026-10-31'));

        $this->assertFalse($relevance->isActiveOn($this->day('2026-10-11')));
        $this->assertTrue($relevance->isActiveOn($this->day('2026-10-12')));
        $this->assertTrue($relevance->isActiveOn($this->day('2026-10-31')));
        $this->assertFalse($relevance->isActiveOn($this->day('2026-11-01')));
    }

    public function testWithoutDatesItHasNoLimit(): void
    {
        $this->assertTrue(Relevance::of(3, null, null)->isActiveOn($this->day('2030-01-01')));
        $this->assertTrue(Relevance::of(1, $this->day('2026-10-01'), null)->isActiveOn($this->day('2031-01-01')));
        $this->assertTrue(Relevance::of(1, null, $this->day('2026-10-01'))->isActiveOn($this->day('2020-01-01')));
    }

    public function testTheTimeOfDayIsIgnored(): void
    {
        $relevance = Relevance::of(1, $this->day('2026-10-12 18:30'), $this->day('2026-10-12 03:00'));

        $this->assertTrue($relevance->isActiveOn($this->day('2026-10-12 23:59')));
    }

    public function testLevelZeroDropsTheDates(): void
    {
        $relevance = Relevance::of(0, $this->day('2026-10-12'), $this->day('2026-10-31'));

        $this->assertNull($relevance->from());
        $this->assertNull($relevance->until());
    }

    /**
     * @dataProvider invalidLevels
     */
    public function testTheLevelGoesFromZeroToThree(int $level): void
    {
        $this->expectException(InvalidProduct::class);

        Relevance::of($level, null, null);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLevels(): iterable
    {
        yield 'negativo' => [-1];
        yield 'cuatro' => [4];
    }

    public function testTheEndCannotBeBeforeTheStart(): void
    {
        $this->expectException(InvalidProduct::class);

        Relevance::of(2, $this->day('2026-10-31'), $this->day('2026-10-12'));
    }
}
