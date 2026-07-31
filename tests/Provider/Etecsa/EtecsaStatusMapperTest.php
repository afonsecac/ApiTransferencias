<?php

namespace App\Tests\Provider\Etecsa;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Etecsa\EtecsaStatusMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Etecsa\EtecsaStatusMapper
 *
 * Casos derivados directamente, rama por rama, del código original de
 * CommunicationSaleService (invokeRechargeCommunication, executeNewSaleInfo,
 * checkStatusOrder) previo al refactor de la Fase 1. El objetivo de esta
 * suite es fijar el comportamiento EXACTO de hoy antes de que
 * CommunicationSaleService deje de contener esa lógica directamente.
 */
class EtecsaStatusMapperTest extends TestCase
{
    private EtecsaStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EtecsaStatusMapper();
    }

    // ---- mapRechargeDispatch (POST /sale/recharge) ----

    public function testRechargeDispatchCodeMinusOneIsAccepted(): void
    {
        $result = $this->mapper->mapRechargeDispatch(['result' => ['code' => -1]]);

        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
        $this->assertNull($result->message);
    }

    public function testRechargeDispatchKnownErrorCodeIsRejectedWithMessage(): void
    {
        $result = $this->mapper->mapRechargeDispatch(['result' => ['code' => 200]]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('200', $result->providerCode);
        $this->assertSame('La venta de productos no pudo ejecutarse satisfactoriamente', $result->message);
    }

    public function testRechargeDispatchUnknownNumericCodeUsesFallbackMessage(): void
    {
        $result = $this->mapper->mapRechargeDispatch(['result' => ['code' => 9999]]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('Unexpected message during the sale', $result->message);
    }

    public function testRechargeDispatchNonNumericCodeUsesFallbackMessage(): void
    {
        $result = $this->mapper->mapRechargeDispatch(['result' => ['code' => 'ABC']]);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('Unexpected message during the sale', $result->message);
    }

    // ---- mapSaleDispatch (POST /sale/package) ----

    public function testSaleDispatchCodeMinusOneIsAccepted(): void
    {
        $result = $this->mapper->mapSaleDispatch(['result' => ['code' => -1]]);

        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
    }

    public function testSaleDispatchPositiveCodeIsFailed(): void
    {
        $result = $this->mapper->mapSaleDispatch(['result' => ['code' => 5]]);

        $this->assertSame(ProviderOutcomeEnum::FAILED, $result->outcome);
        $this->assertSame('5', $result->providerCode);
    }

    public function testSaleDispatchZeroCodeStaysAccepted(): void
    {
        // El original no tiene rama para code === 0: la venta queda sin cambio (PENDING).
        $result = $this->mapper->mapSaleDispatch(['result' => ['code' => 0]]);

        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
    }

    public function testSaleDispatchMissingCodeStaysAccepted(): void
    {
        $result = $this->mapper->mapSaleDispatch(['result' => []]);

        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
    }

    // ---- mapStatusQuery (POST /sale/sale-info) ----

    public function testStatusQueryOrderIdPresentForRechargeCompletes(): void
    {
        $result = $this->mapper->mapStatusQuery(['orderId' => 'ORD-1'], isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertSame('ORD-1', $result->providerReference);
        $this->assertFalse($result->skip);
    }

    public function testStatusQueryOrderIdPresentForPackageWithoutSaleKeyIsSkipped(): void
    {
        $result = $this->mapper->mapStatusQuery(['orderId' => 'ORD-1'], isRecharge: false);

        $this->assertTrue($result->skip);
        $this->assertFalse($result->recordHistory);
        // El original hace `return;` ANTES del flush en este caso concreto: ni
        // siquiera persiste la respuesta cruda recién recibida.
        $this->assertTrue($result->abortWithoutPersisting);
    }

    public function testStatusQueryOrderIdPresentForPackageWithSaleKeyCompletes(): void
    {
        $response = [
            'orderId' => 'ORD-2',
            'fullResponse' => ['Sale' => ['id' => 42]],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: false);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertSame('ORD-2', $result->providerReference);
    }

    public function testStatusQueryCompletedRechargeStateCodeOkCompletesWithoutOrderId(): void
    {
        $response = [
            'status' => 'Completed',
            'fullResponse' => ['saleRecharge' => ['rechargeStateCode' => 'OK']],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
        $this->assertNull($result->providerReference);
    }

    public function testStatusQueryCompletedRechargeStateRealizadaCompletes(): void
    {
        $response = [
            'status' => 'Completed',
            'fullResponse' => ['saleRecharge' => ['rechargeState' => 'Realizada']],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
    }

    public function testStatusQueryRechargeStateCodePendingRemainsPending(): void
    {
        $response = [
            'fullResponse' => ['saleRecharge' => ['rechargeStateCode' => 'PE']],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::PENDING, $result->outcome);
        $this->assertFalse($result->skip);
    }

    public function testStatusQueryRechargeStateCodeProcessingRemainsPending(): void
    {
        $response = [
            'fullResponse' => ['saleRecharge' => ['rechargeStateCode' => 'PR']],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::PENDING, $result->outcome);
    }

    public function testStatusQueryExplicitRejectedStatusWithValueOkIsRejected(): void
    {
        $response = [
            'status' => 'Rejected',
            'result' => ['valueOk' => true],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
    }

    public function testStatusQueryValueOkFalseWithTerminalErrorCodeIsRejected(): void
    {
        $response = [
            'result' => ['valueOk' => false, 'code' => '151'],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('151', $result->providerCode);
        $this->assertSame('El numero de telefono no existe', $result->message);
    }

    public function testStatusQueryValueOkFalseWithNonTerminalErrorCodeStaysPending(): void
    {
        $response = [
            'result' => ['valueOk' => false, 'code' => '300'],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::PENDING, $result->outcome);
        $this->assertFalse($result->skip);
        $this->assertSame('No se pudo obtener el estado de la venta', $result->message);
    }

    public function testStatusQueryValueOkFalseWithCodeMinusOneIsSkipped(): void
    {
        $response = [
            'result' => ['valueOk' => false, 'code' => -1],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertTrue($result->skip);
        $this->assertFalse($result->recordHistory);
        // A diferencia del caso canComplete=false, aquí el original SÍ llega al
        // flush (no hay `return` temprano dentro de esta rama).
        $this->assertFalse($result->abortWithoutPersisting);
    }

    public function testStatusQueryValueOkFalseWithoutCodeIsRejected(): void
    {
        $response = [
            'result' => ['valueOk' => false],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
    }

    public function testStatusQueryNullResultStaysPending(): void
    {
        $result = $this->mapper->mapStatusQuery(['status' => 'Processing'], isRecharge: true);

        $this->assertSame(ProviderOutcomeEnum::PENDING, $result->outcome);
        $this->assertFalse($result->skip);
        // El original registra este histórico sin el payload crudo (tercer
        // argumento por defecto de createHistoricalCommunication).
        $this->assertTrue($result->recordHistoryWithoutData);
    }

    public function testStatusQueryValueOkTrueWithoutMatchingBranchIsSkipped(): void
    {
        // result existe, valueOk=true, pero status no es 'Rejected' y no hay
        // orderId/isCompletedRecharge: el original no ejecuta ninguna rama.
        $response = [
            'status' => 'Processing',
            'result' => ['valueOk' => true],
        ];

        $result = $this->mapper->mapStatusQuery($response, isRecharge: true);

        $this->assertTrue($result->skip);
        $this->assertFalse($result->recordHistory);
        $this->assertFalse($result->abortWithoutPersisting);
    }

    public function testErrorMessageReturnsNullForUnknownCode(): void
    {
        $this->assertNull($this->mapper->errorMessage('777777'));
    }

    public function testErrorMessageReturnsNullForNullCode(): void
    {
        $this->assertNull($this->mapper->errorMessage(null));
    }
}
