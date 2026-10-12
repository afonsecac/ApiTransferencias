<?php

namespace App\Tests\Service;

use App\Service\SaleCheckBackoff;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\SaleCheckBackoff
 */
class SaleCheckBackoffTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function delays(): iterable
    {
        yield 'antes de la primera consulta' => [0, 5];
        yield 'tras la 1.ª' => [1, 15];
        yield 'tras la 2.ª' => [2, 60];
        yield 'tras la 3.ª' => [3, 300];
        yield 'tras la 4.ª' => [4, 900];
        yield 'tras la 5.ª' => [5, 3600];
        yield 'el tope se mantiene en 1 h' => [6, 3600];
        yield 'con 843 consultas sigue en 1 h' => [843, 3600];
    }

    /**
     * @dataProvider delays
     */
    public function testDelayGrowsUntilItReachesTheOneHourCap(int $attempts, int $expectedSeconds): void
    {
        $this->assertSame($expectedSeconds, SaleCheckBackoff::delaySeconds($attempts));
    }

    public function testNegativeAttemptsAreTreatedAsTheFirstOne(): void
    {
        $this->assertSame(5, SaleCheckBackoff::delaySeconds(-3));
    }

    public function testNextCheckAtAddsTheDelayToTheGivenInstant(): void
    {
        $now = new \DateTimeImmutable('2026-10-12 12:00:00');

        $this->assertEquals(new \DateTimeImmutable('2026-10-12 12:01:00'), SaleCheckBackoff::nextCheckAt(2, $now));
    }
}
