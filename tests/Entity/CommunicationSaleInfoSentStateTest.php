<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\CommunicationSaleInfo::wasSentToProvider
 *
 * "Ya se envió al proveedor" lo decide stateProcess: una venta que espera
 * proveedor (AWAITING_PROVIDER) todavía no tiene a quién consultarle el
 * estado, igual que CREATED/SENDING/null.
 */
class CommunicationSaleInfoSentStateTest extends TestCase
{
    /**
     * @dataProvider notSentStates
     */
    public function testSaleNotYetSentToTheProvider(?string $stateProcess): void
    {
        $sale = (new CommunicationSaleRecharge())->setStateProcess($stateProcess);

        $this->assertFalse($sale->wasSentToProvider());
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function notSentStates(): iterable
    {
        yield 'null' => [null];
        yield 'created' => [CommunicationStateEnum::CREATED->value];
        yield 'sending' => ['SENDING'];
        yield 'awaiting provider' => [CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER];
    }

    /**
     * @dataProvider sentStates
     */
    public function testSaleAlreadySentToTheProvider(string $stateProcess): void
    {
        $sale = (new CommunicationSaleRecharge())->setStateProcess($stateProcess);

        $this->assertTrue($sale->wasSentToProvider());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function sentStates(): iterable
    {
        yield 'pending' => [CommunicationStateEnum::PENDING->value];
        yield 'completed' => [CommunicationStateEnum::COMPLETED->value];
        yield 'failed' => [CommunicationStateEnum::FAILED->value];
    }
}
