<?php

namespace App\Tests\Provider\Csq;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Csq\CsqStatusMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Csq\CsqStatusMapper
 *
 * Todos los payloads son respuestas reales capturadas en vivo contra CSQ
 * (2026-08-10), no inventadas — ver docblock de CsqStatusMapper para el
 * porqué de la regla (rc como único criterio, finalstatus descartado).
 */
class CsqStatusMapperTest extends TestCase
{
    private CsqStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CsqStatusMapper();
    }

    // ---- mapDispatch ----

    public function testMapDispatchReturnsCompletedOnRcZero(): void
    {
        $result = $this->mapper->mapDispatch([
            'rc' => 0,
            'items' => [[
                'finalstatus' => 10,
                'resultcode' => '10',
                'resultmessage' => 'Operación efectuada correctamente',
                'supplierreference' => '1786346034143',
                'suppliertoken' => '',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertSame('1786346034143', $result->providerReference);
        $this->assertSame('10', $result->providerCode);
        $this->assertSame('Operación efectuada correctamente', $result->message);
    }

    public function testMapDispatchReturnsRejectedOnBusinessError(): void
    {
        $result = $this->mapper->mapDispatch([
            'rc' => -1,
            'items' => [[
                'finalstatus' => -1,
                'resultcode' => '927',
                'resultmessage' => 'Importe incorrecto para la ruta 993',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('927', $result->providerCode);
        $this->assertNull($result->providerReference);
    }

    public function testMapDispatchReturnsRejectedOnGenericSystemError(): void
    {
        // finalstatus (991) coincide con resultcode aquí, pero NO en el caso
        // anterior (927 vs finalstatus -1) — confirma que no hay relación
        // fija y por eso no se usa para decidir el outcome.
        $result = $this->mapper->mapDispatch([
            'rc' => -1,
            'items' => [[
                'finalstatus' => 991,
                'resultcode' => '991',
                'resultmessage' => 'Error de sistema',
                'supplierreference' => '',
                'suppliertoken' => '',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertNull($result->providerReference, 'supplierreference vacío debe normalizarse a null');
    }

    public function testMapDispatchReturnsRejectedOnValidationErrorWithoutResultcode(): void
    {
        // Error de validación de campos (amountToSendX100 + destinationAmountX100
        // juntos): la respuesta real no trae resultcode en absoluto.
        $result = $this->mapper->mapDispatch([
            'rc' => -1,
            'items' => [[
                'finalstatus' => 0,
                'resultmessage' => 'amountToSendX100 and destinationAmountX100 must not be informed at the same time, please inform only one of it',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertNull($result->providerCode);
    }

    public function testMapDispatchHandlesMissingItemsGracefully(): void
    {
        $result = $this->mapper->mapDispatch(['rc' => -1]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertNull($result->providerCode);
        $this->assertNull($result->message);
    }

    // ---- mapStatusQuery ----

    public function testMapStatusQueryReturnsCompletedOnRcZero(): void
    {
        $result = $this->mapper->mapStatusQuery([
            'rc' => 0,
            'items' => [[
                'finalstatus' => 10,
                'resultcode' => '10',
                'resultmessage' => 'Operación efectuada correctamente',
                'supplierreference' => '1786346034143',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertSame('1786346034143', $result->providerReference);
    }

    public function testMapStatusQueryReturnsUnknownNotRejectedOnRcNonZero(): void
    {
        // Transaction Info tiene delay de indexación: consultado justo
        // después de crear la transacción responde "900 Invalid parameters"
        // (confirmado también contra una transacción real, no solo una
        // inexistente) — nunca debe interpretarse como un rechazo real de
        // la venta original, aunque más tarde (indexado ya) sí responda bien.
        $result = $this->mapper->mapStatusQuery([
            'rc' => -1,
            'items' => [[
                'finalstatus' => 0,
                'resultcode' => '900',
                'resultmessage' => 'Invalid parameters',
            ]],
        ]);

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }
}
