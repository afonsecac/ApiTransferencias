<?php

namespace App\Tests\Functional\Provider;

use App\Service\CommunicationsDispatchService;
use App\Service\DispatchPauseLedger;

/**
 * @covers \App\Service\DispatchPauseLedger
 *
 * Registro de los periodos en que la plataforma tuvo la entrega
 * deshabilitada (communications.dispatch.enabled = '0'): el tope de espera
 * de una venta sin proveedor no corre durante esos periodos. Contra
 * Postgres real porque persiste en sys_config.
 */
class DispatchPauseLedgerFunctionalTest extends ProviderFunctionalTestCase
{
    private \DateTimeImmutable $base;

    protected function setUp(): void
    {
        parent::setUp();
        // Hora fija por test: dos `new DateTimeImmutable()` distintos
        // difieren por segundos y desafinarían las aserciones exactas.
        $this->base = new \DateTimeImmutable('now');
    }

    private function ledger(): DispatchPauseLedger
    {
        return self::getContainer()->get(DispatchPauseLedger::class);
    }

    private function dispatchEnabled(bool $enabled): void
    {
        $this->setSysConfig(CommunicationsDispatchService::CONFIG_KEY, $enabled ? '1' : '0');
    }

    private function ago(string $interval): \DateTimeImmutable
    {
        return $this->base->modify('-' . $interval);
    }

    public function testNoPausesMeansNoPausedTime(): void
    {
        $this->dispatchEnabled(true);
        $this->ledger()->observe($this->ago('1 hour'));

        $this->assertSame(0, $this->ledger()->pausedSeconds($this->ago('10 hours'), $this->base));
    }

    public function testAClosedPauseCountsItsFullDuration(): void
    {
        $this->dispatchEnabled(false);
        $this->ledger()->observe($this->ago('5 hours'));
        $this->dispatchEnabled(true);
        $this->ledger()->observe($this->ago('2 hours'));

        $this->assertSame(3 * 3600, $this->ledger()->pausedSeconds($this->ago('10 hours'), $this->base));
    }

    public function testOnlyTheOverlapWithTheRequestedWindowCounts(): void
    {
        $this->dispatchEnabled(false);
        $this->ledger()->observe($this->ago('5 hours'));
        $this->dispatchEnabled(true);
        $this->ledger()->observe($this->ago('2 hours'));

        // La ventana arranca 1 h después del inicio de la pausa: quedan 2 h.
        $this->assertSame(2 * 3600, $this->ledger()->pausedSeconds($this->ago('4 hours'), $this->base));
        // La ventana termina a mitad de la pausa: 1 h.
        $this->assertSame(3600, $this->ledger()->pausedSeconds($this->ago('10 hours'), $this->ago('4 hours')));
    }

    public function testAnOpenPauseCountsUntilTheEndOfTheWindowAndIsNotDuplicated(): void
    {
        $this->dispatchEnabled(false);
        $this->ledger()->observe($this->ago('6 hours'));
        $this->ledger()->observe($this->ago('3 hours'));
        $this->ledger()->observe($this->ago('1 hour'));

        $this->assertSame(6 * 3600, $this->ledger()->pausedSeconds($this->ago('10 hours'), $this->base));
    }

    public function testClosedPausesOlderThan30DaysArePrunedOnTheNextWrite(): void
    {
        $this->dispatchEnabled(false);
        $this->ledger()->observe($this->ago('40 days'));
        $this->dispatchEnabled(true);
        $this->ledger()->observe($this->ago('39 days'));
        // Una pausa nueva fuerza una escritura que poda la antigua.
        $this->dispatchEnabled(false);
        $this->ledger()->observe($this->ago('1 hour'));

        $this->assertSame(0, $this->ledger()->pausedSeconds($this->ago('45 days'), $this->ago('30 days')));
    }
}
