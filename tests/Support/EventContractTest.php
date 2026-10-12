<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Tests\Support\Fixtures\ExampleOrderPlaced;
use App\Tests\Support\Fixtures\ExampleOrderPlacedWithExtraField;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EventContractTest extends KernelTestCase
{
    use AssertsEventContract;

    private function event(): ExampleOrderPlaced
    {
        return new ExampleOrderPlaced(
            eventId: '0b0d6a52-3a7c-4c1e-9f0e-1d2c3b4a5f60',
            occurredAt: new \DateTimeImmutable('2026-10-12T10:00:00+00:00'),
            tenantId: 7,
            orderId: 1234,
            lines: [['productId' => 10, 'quantity' => 2], ['productId' => 11, 'quantity' => 1]],
        );
    }

    public function testEventMatchesItsStoredContract(): void
    {
        $this->assertEventContract($this->event());
    }

    public function testEventSurvivesTheTransportRoundTrip(): void
    {
        $this->assertEventRoundTrip($this->event());
    }

    public function testChangingTheShapeOfAnEventBreaksTheContract(): void
    {
        $changed = new ExampleOrderPlacedWithExtraField(
            eventId: '0b0d6a52-3a7c-4c1e-9f0e-1d2c3b4a5f60',
            occurredAt: new \DateTimeImmutable('2026-10-12T10:00:00+00:00'),
            tenantId: 7,
            orderId: 1234,
            lines: [['productId' => 10, 'quantity' => 2]],
            currency: 'EUR',
        );

        $this->expectException(AssertionFailedError::class);
        $this->assertEventContract($changed, ExampleOrderPlacedWithExtraField::CONTRACT_NAME);
    }

    public function testAMissingContractFailsWithAnExplicitMessage(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No existe el contrato');
        $this->assertEventContract($this->event(), 'ContratoQueNoExiste');
    }
}
