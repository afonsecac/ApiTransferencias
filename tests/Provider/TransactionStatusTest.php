<?php

namespace App\Tests\Provider;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusResult;
use App\Provider\TransactionStatus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\TransactionStatus
 */
class TransactionStatusTest extends TestCase
{
    // ---- fromDispatch / fromStatus ----

    public function testFromDispatchBuildsAProviderSourcedV2Envelope(): void
    {
        $result = new ProviderDispatchResult(
            outcome: ProviderOutcomeEnum::COMPLETED,
            providerReference: '1786346034143',
            providerCode: '10',
            message: 'Operación efectuada correctamente',
            raw: ['rc' => 0, 'items' => [['resultcode' => '10']]],
        );

        $envelope = TransactionStatus::fromDispatch($result, 'CSQ');

        $this->assertSame(2, $envelope['schemaVersion']);
        $this->assertSame('provider', $envelope['source']);
        $this->assertSame('COMPLETED', $envelope['outcome']);
        $this->assertSame('CSQ', $envelope['provider']);
        $this->assertSame('1786346034143', $envelope['providerReference']);
        $this->assertSame('10', $envelope['providerCode']);
        $this->assertSame($result->raw, $envelope['raw']);
        $this->assertArrayHasKey('occurredAt', $envelope);
    }

    public function testFromStatusBuildsAProviderSourcedV2Envelope(): void
    {
        $result = new ProviderStatusResult(
            outcome: ProviderOutcomeEnum::UNKNOWN,
            raw: ['rc' => -1, 'items' => [['resultcode' => '900']]],
        );

        $envelope = TransactionStatus::fromStatus($result, 'CSQ');

        $this->assertSame('provider', $envelope['source']);
        $this->assertSame('UNKNOWN', $envelope['outcome']);
        $this->assertSame($result->raw, $envelope['raw']);
    }

    public function testContextIsOmittedWhenEmptyButIncludedWhenGiven(): void
    {
        $result = new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED);

        $withoutContext = TransactionStatus::fromDispatch($result, null);
        $this->assertArrayNotHasKey('context', $withoutContext);

        $withContext = TransactionStatus::fromDispatch($result, null, ['balance' => 10.0]);
        $this->assertSame(['balance' => 10.0], $withContext['context']);
    }

    // ---- internal ----

    public function testInternalAlwaysHasEmptyRaw(): void
    {
        $envelope = TransactionStatus::internal(
            ProviderOutcomeEnum::REJECTED,
            'INTERNAL_INSUFFICIENT_BALANCE',
            "The balance aren`t sufficient",
        );

        $this->assertSame('internal', $envelope['source']);
        $this->assertSame('REJECTED', $envelope['outcome']);
        $this->assertSame('INTERNAL_INSUFFICIENT_BALANCE', $envelope['providerCode']);
        $this->assertSame([], $envelope['raw']);
        $this->assertNull($envelope['providerReference']);
    }

    // ---- internalPreserving ----

    public function testInternalPreservingKeepsRawAndReferenceWhenPreviousWasProviderSourced(): void
    {
        $previous = TransactionStatus::fromDispatch(
            new ProviderDispatchResult(
                outcome: ProviderOutcomeEnum::ACCEPTED,
                providerReference: 'REF-1',
                raw: ['status' => 'accepted'],
            ),
            'DTONE',
        );

        $envelope = TransactionStatus::internalPreserving(
            $previous,
            ProviderOutcomeEnum::UNKNOWN,
            'INTERNAL_UNEXPECTED_ERROR',
            'boom',
        );

        $this->assertSame('internal', $envelope['source']);
        $this->assertSame('UNKNOWN', $envelope['outcome']);
        $this->assertSame(['status' => 'accepted'], $envelope['raw']);
        $this->assertSame('REF-1', $envelope['providerReference']);
        $this->assertSame('DTONE', $envelope['provider']);
    }

    public function testInternalPreservingWrapsLegacyV1PayloadIntoRaw(): void
    {
        $legacyEtecsaPayload = [
            'orderId' => 123,
            'result' => ['valueOk' => true, 'message' => 'OK'],
            'fullResponse' => ['saleRecharge' => ['phoneNumber' => '5350000000']],
        ];

        $envelope = TransactionStatus::internalPreserving(
            $legacyEtecsaPayload,
            ProviderOutcomeEnum::PENDING,
            'INTERNAL_PROVIDER_HTTP_400',
            'La orden aun esta en procesamiento',
        );

        // v1 no tiene 'source' propio -> no se preserva nada (no sabemos si
        // fue un round-trip real), pero tampoco explota.
        $this->assertSame('internal', $envelope['source']);
        $this->assertSame([], $envelope['raw']);
    }

    public function testInternalPreservingWithEmptyCurrentDoesNotExplode(): void
    {
        $envelope = TransactionStatus::internalPreserving([], ProviderOutcomeEnum::FAILED, 'INTERNAL_X');

        $this->assertSame([], $envelope['raw']);
        $this->assertNull($envelope['providerReference']);
    }

    // ---- withRetry ----

    public function testWithRetryAddsRetryBlockWithoutTouchingTheRestOfAV2Envelope(): void
    {
        $original = TransactionStatus::fromStatus(
            new ProviderStatusResult(outcome: ProviderOutcomeEnum::UNKNOWN, raw: ['resultcode' => '900']),
            'CSQ',
        );

        $envelope = TransactionStatus::withRetry(
            $original,
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_GATEWAY_NOT_FOUND_RETRY',
            'Not found in ApiComm, resending',
            ['count' => 1, 'lastAttemptAt' => '2026-08-12T10:00:00+00:00'],
        );

        $this->assertSame('RETRYABLE', $envelope['outcome']);
        $this->assertSame(['count' => 1, 'lastAttemptAt' => '2026-08-12T10:00:00+00:00'], $envelope['retry']);
        $this->assertSame(['resultcode' => '900'], $envelope['raw']);
        $this->assertSame('CSQ', $envelope['provider']);
    }

    public function testWithRetryWrapsLegacyV1PayloadAsRaw(): void
    {
        $legacyEtecsaPayload = ['orderId' => 1, 'status' => 'Pending'];

        $envelope = TransactionStatus::withRetry(
            $legacyEtecsaPayload,
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_GATEWAY_NOT_FOUND_RETRY',
            null,
            ['count' => 1],
        );

        $this->assertTrue(TransactionStatus::isV2($envelope));
        $this->assertSame($legacyEtecsaPayload, $envelope['raw']);
        $this->assertSame(['count' => 1], $envelope['retry']);
    }

    // ---- carryRetryBlock / failoverFromOf ----

    /**
     * fromDispatch()/fromStatus() arman un sobre nuevo desde cero — sin
     * carryRetryBlock(), un segundo intento de despacho perdería el
     * marcador de failover del intento anterior y el "un solo salto por
     * venta" de SaleProviderFailoverService dejaría de cumplirse.
     */
    public function testCarryRetryBlockPreservesThePreviousRetryBlockWhenTheNewEnvelopeHasNone(): void
    {
        $previous = TransactionStatus::withRetry(
            TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED), 'CSQ'),
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_PROVIDER_FAILOVER',
            'Provider rejected',
            ['failoverFrom' => 'CSQ', 'failoverTo' => 'DTONE'],
        );

        $new = TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED), 'DTONE');
        $carried = TransactionStatus::carryRetryBlock($previous, $new);

        $this->assertSame(['failoverFrom' => 'CSQ', 'failoverTo' => 'DTONE'], $carried['retry']);
        // El resto del sobre nuevo (provider, outcome) no se toca.
        $this->assertSame('DTONE', $carried['provider']);
    }

    public function testCarryRetryBlockDoesNotOverwriteARetryBlockAlreadyPresentInTheNewEnvelope(): void
    {
        $previous = TransactionStatus::withRetry(
            TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED), 'CSQ'),
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_PROVIDER_FAILOVER',
            'Provider rejected',
            ['failoverFrom' => 'CSQ', 'failoverTo' => 'DTONE'],
        );

        $new = TransactionStatus::withRetry(
            TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::RETRYABLE), 'DTONE'),
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_GATEWAY_NOT_FOUND_RETRY',
            null,
            ['count' => 1],
        );

        $carried = TransactionStatus::carryRetryBlock($previous, $new);

        $this->assertSame(['count' => 1], $carried['retry']);
    }

    public function testCarryRetryBlockIsANoOpWhenNeitherEnvelopeHasARetryBlock(): void
    {
        $previous = TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED), 'CSQ');
        $new = TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED), 'CSQ');

        $carried = TransactionStatus::carryRetryBlock($previous, $new);

        $this->assertArrayNotHasKey('retry', $carried);
    }

    public function testFailoverFromOfReturnsNullWithoutARetryBlock(): void
    {
        $status = TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED), 'CSQ');

        $this->assertNull(TransactionStatus::failoverFromOf($status));
    }

    public function testFailoverFromOfReadsTheMarker(): void
    {
        $status = TransactionStatus::withRetry(
            TransactionStatus::fromDispatch(new ProviderDispatchResult(outcome: ProviderOutcomeEnum::REJECTED), 'CSQ'),
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_PROVIDER_FAILOVER',
            'Provider rejected',
            ['failoverFrom' => 'CSQ', 'failoverTo' => 'DTONE'],
        );

        $this->assertSame('CSQ', TransactionStatus::failoverFromOf($status));
    }

    // ---- lectura: isV2 / rawOf / outcomeOf ----

    public function testIsV2IsFalseForEmptyArray(): void
    {
        $this->assertFalse(TransactionStatus::isV2([]));
    }

    public function testIsV2IsFalseForRealLegacyEtecsaPayload(): void
    {
        $legacy = ['orderId' => 1, 'result' => ['valueOk' => true, 'message' => 'OK'], 'status' => 'Approved'];

        $this->assertFalse(TransactionStatus::isV2($legacy));
    }

    public function testIsV2IsTrueForFactoryOutput(): void
    {
        $envelope = TransactionStatus::internal(ProviderOutcomeEnum::FAILED, 'INTERNAL_X');

        $this->assertTrue(TransactionStatus::isV2($envelope));
    }

    public function testRawOfReturnsTheWholeArrayForV1(): void
    {
        $legacy = ['orderId' => 1, 'status' => 'Approved'];

        $this->assertSame($legacy, TransactionStatus::rawOf($legacy));
    }

    public function testRawOfReturnsTheRawKeyForV2(): void
    {
        $envelope = TransactionStatus::fromDispatch(
            new ProviderDispatchResult(outcome: ProviderOutcomeEnum::COMPLETED, raw: ['a' => 1]),
            'CSQ',
        );

        $this->assertSame(['a' => 1], TransactionStatus::rawOf($envelope));
    }

    public function testOutcomeOfReturnsNullForV1(): void
    {
        $this->assertNull(TransactionStatus::outcomeOf(['status' => 'Approved']));
    }

    public function testOutcomeOfParsesTheEnumForV2(): void
    {
        $envelope = TransactionStatus::internal(ProviderOutcomeEnum::REJECTED, 'INTERNAL_X');

        $this->assertSame(ProviderOutcomeEnum::REJECTED, TransactionStatus::outcomeOf($envelope));
    }

    // ---- retryCountOf / lastRetryAtOf: doble fallback CRÍTICO ----

    public function testRetryCountOfReadsTheLegacyRootKey(): void
    {
        // Forma legacy escrita por el código ANTES de este cambio — debe
        // seguir leyéndose correctamente o el contador se reinicia a 0 y la
        // venta reenvía indefinidamente cada 4h (riesgo de doble cobro).
        $legacyInFlight = ['retryCount' => 2, 'lastRetryAt' => '2026-08-12T06:00:00+00:00'];

        $this->assertSame(2, TransactionStatus::retryCountOf($legacyInFlight));
        $this->assertSame('2026-08-12T06:00:00+00:00', TransactionStatus::lastRetryAtOf($legacyInFlight));
    }

    public function testRetryCountOfReadsTheNewNestedKey(): void
    {
        $envelope = TransactionStatus::withRetry(
            TransactionStatus::internal(ProviderOutcomeEnum::UNKNOWN, 'X'),
            ProviderOutcomeEnum::RETRYABLE,
            'INTERNAL_GATEWAY_NOT_FOUND_RETRY',
            null,
            ['count' => 3, 'lastAttemptAt' => '2026-08-12T10:00:00+00:00'],
        );

        $this->assertSame(3, TransactionStatus::retryCountOf($envelope));
        $this->assertSame('2026-08-12T10:00:00+00:00', TransactionStatus::lastRetryAtOf($envelope));
    }

    public function testRetryCountOfDefaultsToZeroWhenAbsent(): void
    {
        $this->assertSame(0, TransactionStatus::retryCountOf([]));
        $this->assertNull(TransactionStatus::lastRetryAtOf([]));
    }
}
