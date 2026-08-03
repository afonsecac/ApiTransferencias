<?php

namespace App\Tests\Provider\DTOne;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\DTOne\DTOneStatusMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\DTOne\DTOneStatusMapper
 *
 * El shape real de DTOne (confirmado en vivo el 2026-08-03 y contra
 * developers.dtone.com/reference/gettransactionbyid) es anidado:
 * `status: {id, message, class: {id, message}}` — `class.id` es una de las
 * 8 clases documentadas (lo que decide el outcome); `id` es un código
 * mucho más granular (p.ej. 20000) que solo se conserva como providerCode.
 */
class DTOneStatusMapperTest extends TestCase
{
    private const DETAIL_STATUS_ID = 20000;

    private DTOneStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DTOneStatusMapper();
    }

    /**
     * @return iterable<string, array{int, ProviderOutcomeEnum, ProviderOutcomeEnum}>
     */
    public static function statusClassProvider(): iterable
    {
        // [class.id, outcome esperado en mapDispatch, outcome esperado en mapStatusQuery]
        yield 'CREATED' => [1, ProviderOutcomeEnum::ACCEPTED, ProviderOutcomeEnum::PENDING];
        yield 'CONFIRMED' => [2, ProviderOutcomeEnum::ACCEPTED, ProviderOutcomeEnum::PENDING];
        yield 'REJECTED' => [3, ProviderOutcomeEnum::REJECTED, ProviderOutcomeEnum::REJECTED];
        yield 'CANCELLED' => [4, ProviderOutcomeEnum::REJECTED, ProviderOutcomeEnum::REJECTED];
        yield 'SUBMITTED' => [5, ProviderOutcomeEnum::ACCEPTED, ProviderOutcomeEnum::PENDING];
        yield 'COMPLETED' => [7, ProviderOutcomeEnum::COMPLETED, ProviderOutcomeEnum::COMPLETED];
        yield 'REVERSED' => [8, ProviderOutcomeEnum::UNKNOWN, ProviderOutcomeEnum::UNKNOWN];
        yield 'DECLINED' => [9, ProviderOutcomeEnum::REJECTED, ProviderOutcomeEnum::REJECTED];
        yield 'clase desconocida' => [999, ProviderOutcomeEnum::UNKNOWN, ProviderOutcomeEnum::UNKNOWN];
    }

    /**
     * @dataProvider statusClassProvider
     */
    public function testMapDispatchUsesAcceptedForStillProcessingClasses(int $classId, ProviderOutcomeEnum $dispatchExpected): void
    {
        $result = $this->mapper->mapDispatch([
            'id' => 'txn-123',
            'status' => ['id' => self::DETAIL_STATUS_ID, 'message' => 'some message', 'class' => ['id' => $classId, 'message' => 'class message']],
        ]);

        $this->assertSame($dispatchExpected, $result->outcome);
        $this->assertSame('txn-123', $result->providerReference);
        // providerCode viene del status.id granular, NUNCA del class.id.
        $this->assertSame((string) self::DETAIL_STATUS_ID, $result->providerCode);
        $this->assertSame('some message', $result->message);
    }

    /**
     * @dataProvider statusClassProvider
     */
    public function testMapStatusQueryUsesPendingForStillProcessingClasses(int $classId, ProviderOutcomeEnum $dispatchExpected, ProviderOutcomeEnum $statusQueryExpected): void
    {
        $result = $this->mapper->mapStatusQuery([
            'id' => 'txn-456',
            'status' => ['id' => self::DETAIL_STATUS_ID, 'message' => 'some message', 'class' => ['id' => $classId, 'message' => 'class message']],
        ]);

        $this->assertSame($statusQueryExpected, $result->outcome);
        $this->assertSame('txn-456', $result->providerReference);
        $this->assertSame((string) self::DETAIL_STATUS_ID, $result->providerCode);
    }

    /**
     * Antes del fix del 2026-08-03 se leía `status.id` (el granular, aquí
     * 20000) en vez de `status.class.id` para decidir el outcome — como
     * 20000 no es ninguno de los 8 valores de clase, TODA transacción real
     * y exitosa de DTOne caía en el `default` → UNKNOWN, sin importar cuál
     * fuera el estado real.
     */
    public function testMapDispatchDoesNotConfuseDetailStatusIdWithClassId(): void
    {
        $result = $this->mapper->mapDispatch([
            'id' => 'txn-real',
            'status' => ['id' => self::DETAIL_STATUS_ID, 'message' => 'CONFIRMED', 'class' => ['id' => 2, 'message' => 'CONFIRMED']],
        ]);

        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
    }

    public function testMissingStatusObjectMapsToUnknown(): void
    {
        $result = $this->mapper->mapDispatch(['id' => 'txn-789']);

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
        $this->assertNull($result->providerCode);
        $this->assertNull($result->message);
    }

    public function testMissingClassMapsToUnknown(): void
    {
        // status.id presente pero sin class anidado — no debe interpretarse
        // como si status.id fuera la clase.
        $result = $this->mapper->mapDispatch([
            'id' => 'txn-noclass',
            'status' => ['id' => 2, 'message' => 'sin clase'],
        ]);

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }

    public function testPreservesRawResponse(): void
    {
        $response = ['id' => 'txn-1', 'status' => ['id' => self::DETAIL_STATUS_ID, 'message' => 'ok', 'class' => ['id' => 7, 'message' => 'COMPLETED']], 'extra' => 'field'];

        $result = $this->mapper->mapStatusQuery($response);

        $this->assertSame($response, $result->raw);
    }
}
