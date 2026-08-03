<?php

namespace App\Tests\Provider;

use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Provider\ProviderOutcomeMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\ProviderOutcomeMapper
 */
class ProviderOutcomeMapperTest extends TestCase
{
    private ProviderOutcomeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProviderOutcomeMapper();
    }

    /**
     * @return array<string, array{ProviderOutcomeEnum, CommunicationStateEnum, string}>
     */
    public static function outcomeProvider(): array
    {
        return [
            'accepted' => [ProviderOutcomeEnum::ACCEPTED, CommunicationStateEnum::PENDING, 'Pending'],
            'pending' => [ProviderOutcomeEnum::PENDING, CommunicationStateEnum::PENDING, 'Pending'],
            'unknown never resets to created' => [ProviderOutcomeEnum::UNKNOWN, CommunicationStateEnum::PENDING, 'Pending'],
            'completed' => [ProviderOutcomeEnum::COMPLETED, CommunicationStateEnum::COMPLETED, 'Completed'],
            'rejected' => [ProviderOutcomeEnum::REJECTED, CommunicationStateEnum::REJECTED, 'Rejected'],
            'failed' => [ProviderOutcomeEnum::FAILED, CommunicationStateEnum::FAILED, 'Failed'],
            'retryable allows resend' => [ProviderOutcomeEnum::RETRYABLE, CommunicationStateEnum::PENDING, 'Created'],
        ];
    }

    /**
     * @dataProvider outcomeProvider
     */
    public function testMapping(ProviderOutcomeEnum $outcome, CommunicationStateEnum $expectedState, string $expectedStateProcess): void
    {
        $this->assertSame($expectedState, $this->mapper->toState($outcome));
        $this->assertSame($expectedStateProcess, $this->mapper->toStateProcess($outcome));
    }

    public function testUnknownNeverProducesCreatedStateProcess(): void
    {
        // Regla crítica: UNKNOWN jamás debe permitir un reenvío al proveedor.
        $this->assertNotSame(
            CommunicationStateEnum::CREATED->value,
            $this->mapper->toStateProcess(ProviderOutcomeEnum::UNKNOWN)
        );
    }

    public function testShouldScheduleCheckOnlyForInFlightOutcomes(): void
    {
        $this->assertTrue($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::ACCEPTED));
        $this->assertTrue($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::PENDING));
        $this->assertTrue($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::UNKNOWN));
        $this->assertFalse($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::COMPLETED));
        $this->assertFalse($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::REJECTED));
        $this->assertFalse($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::FAILED));
        $this->assertFalse($this->mapper->shouldScheduleCheck(ProviderOutcomeEnum::RETRYABLE));
    }

    public function testIsTerminal(): void
    {
        $this->assertTrue($this->mapper->isTerminal(ProviderOutcomeEnum::COMPLETED));
        $this->assertTrue($this->mapper->isTerminal(ProviderOutcomeEnum::REJECTED));
        $this->assertTrue($this->mapper->isTerminal(ProviderOutcomeEnum::FAILED));
        $this->assertFalse($this->mapper->isTerminal(ProviderOutcomeEnum::PENDING));
        $this->assertFalse($this->mapper->isTerminal(ProviderOutcomeEnum::UNKNOWN));
        $this->assertFalse($this->mapper->isTerminal(ProviderOutcomeEnum::ACCEPTED));
        $this->assertFalse($this->mapper->isTerminal(ProviderOutcomeEnum::RETRYABLE));
    }
}
