<?php

namespace App\Provider\Etecsa;

use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusResult;

/**
 * Traduce las respuestas crudas del gateway ApiComm/ETECSA a los DTOs
 * neutrales de la abstracción de proveedor. Absorbe:
 *
 *  - La tabla ETECSA_INFO_ERROR (antes CommunicationSaleService::ETECSA_INFO_ERROR),
 *    ahora almacenada en UTF-8 correcto. El código legado guardaba estos
 *    mensajes mal codificados (mojibake) y los corregía de forma inconsistente
 *    en dos sitios distintos con mb_convert_encoding() en direcciones opuestas
 *    (uno ISO-8859-1→UTF-8, el otro UTF-8→ISO-8859-1). Al normalizar aquí de
 *    una vez, ningún llamador necesita volver a tocar la codificación.
 *
 *  - El parseo de la respuesta de /sale/recharge (mapRechargeDispatch),
 *    /sale/package (mapSaleDispatch) y /sale/sale-info (mapStatusQuery) —
 *    antes repartido en CommunicationSaleService::invokeRechargeCommunication(),
 *    ::executeNewSaleInfo() y el else-if de ::checkStatusOrder().
 *
 * mapStatusQuery() reproduce, rama por rama, la lógica original de
 * checkStatusOrder(), incluidos los casos en los que el código original no
 * modificaba la venta en absoluto (ProviderStatusResult::$skip = true).
 */
final class EtecsaStatusMapper
{
    private const ETECSA_INFO_ERROR = [
        '100' => 'No se especificaron productos para la venta',
        '101' => 'Se envió una Recarga junto a compra de artículos solamente.',
        '102' => 'Se envió más de una Activación en la misma transacción.',
        '103' => 'Se envió más de una Recarga en la misma transacción.',
        '104' => 'Los datos del cliente no son correctos.',
        '105' => 'Monto de la recarga asociada a una Activación no permitido',
        '107' => 'El identificador de la transacción no es válido',
        '108' => 'Monto de la recarga no permitido',
        '109' => 'El paquete especificado no está habilitado',
        '110' => 'El paquete especificado no existe',
        '111' => 'No fue especificado un paquete para la venta',
        '112' => 'Los datos para la modificacion de la venta no son válidos',
        '151' => 'El numero de telefono no existe',
        '152' => 'Servicio celular del cliente en estado no válido para ser recargado',
        '153' => 'Tipo de servicio celular del cliente no válido para ser recargado',
        '198' => 'Combinación de productos no válida',
        '199' => 'Datos de la venta incorrectos',
        '200' => 'La venta de productos no pudo ejecutarse satisfactoriamente',
        '201' => 'La Activación no pudo ejecutarse satisfactoriamente',
        '202' => 'La Recarga no pudo ejecutarse satisfactoriamente.',
        '203' => 'La venta de Terminales no pudo ejecutarse satisfactoriamente',
        '204' => 'La venta de Accesorios no pudo ejecutarse satisfactoriamente',
        '205' => 'No se pudo cambiar la contraseña de usuario',
        '206' => 'La venta del paquete no pudo ejecutarse satisfactoriamente',
        '207' => 'No se pudieron modificar los datos de venta',
        '208' => 'No se pudo cancelar la venta',
        '209' => 'No se pudo realizar el chequeo de los datos de la activación de línea celular',
        '300' => 'No se pudo obtener el estado de la venta',
        '301' => 'No se pudieron obtener los datos de la venta',
        '302' => 'No se pudieron obtener los datos de las ventas',
        '303' => 'No se pudieron obtener los datos de las oficinas comerciales',
        '304' => 'No se pudieron obtener los privilegios del usuario',
        '305' => 'No se pudieron obtener los datos de las provincias',
        '306' => 'No se pudieron obtener los datos del distribuidor',
        '307' => 'No se pudieron obtener los paquetes para la venta',
        '308' => 'No se pudieron obtener los elementos de paquetes',
        '309' => 'No se pudieron obtener los datos de los tipos de identificación',
        '310' => 'No se pudieron obtener los datos de los Municipios',
        '311' => 'No se pudieron obtener los datos de las Nacionalidades',
        '901' => 'El distribuidor no tiene permitida la venta de Activaciones',
        '902' => 'El distribuidor no tiene permitida la venta de Recargas',
        '903' => 'El distribuidor no tiene permitida la venta de Terminales y Accesorios',
        '904' => 'El distribuidor no tiene permitida la venta de Activaciones Temportales TURISTA',
        '905' => 'El distribuidor no tiene permitida la venta de Recursos TURISTA',
        '-1' => 'Su transaccion se esta procesando',
        '-2' => 'Su orden aun se esta procesando',
        '-3' => 'Ha ocurrido una falla durante el proceso de procesamiento de la recarga. Pronto nos pondremos en contacto.',
    ];

    public function errorMessage(string|int|null $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::ETECSA_INFO_ERROR[(string) $code] ?? null;
    }

    /**
     * Parsea la respuesta de POST /sale/recharge.
     * code === -1 → aceptada, aún procesando (ACCEPTED). Cualquier otro
     * código → rechazo confirmado por el gateway (REJECTED).
     *
     * @param array<string, mixed> $raw
     */
    public function mapRechargeDispatch(array $raw): ProviderDispatchResult
    {
        $result = (object) ($raw['result'] ?? []);
        $code = $result->code ?? null;

        if ($code !== null && (int) $code === -1) {
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
        }

        $message = (is_numeric($code) ? $this->errorMessage($code) : null) ?? 'Unexpected message during the sale';

        return new ProviderDispatchResult(
            outcome: ProviderOutcomeEnum::REJECTED,
            providerCode: $code !== null ? (string) $code : null,
            message: $message,
            raw: $raw,
        );
    }

    /**
     * Parsea la respuesta de POST /sale/package.
     * code === -1 → aceptada, aún procesando. code > 0 → FAILED (así lo
     * marcaba el código original, distinto de REJECTED usado en recargas —
     * asimetría preexistente que se conserva tal cual).
     *
     * @param array<string, mixed> $raw
     */
    public function mapSaleDispatch(array $raw): ProviderDispatchResult
    {
        $result = (object) ($raw['result'] ?? []);
        $code = property_exists($result, 'code') ? (int) $result->code : null;

        if ($code === -1) {
            return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
        }

        if ($code !== null && $code > 0) {
            return new ProviderDispatchResult(
                outcome: ProviderOutcomeEnum::FAILED,
                providerCode: (string) $code,
                raw: $raw,
            );
        }

        // Ninguna rama del original coincide con code <= 0 distinto de -1, ni con
        // ausencia de code: la venta queda sin cambio de estado (sigue PENDING).
        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED, raw: $raw);
    }

    /**
     * Parsea la respuesta de POST /sale/sale-info. Reproduce, rama por rama,
     * el else-if original de CommunicationSaleService::checkStatusOrder().
     *
     * @param array<string, mixed> $response
     */
    public function mapStatusQuery(array $response, bool $isRecharge): ProviderStatusResult
    {
        $responseInfo = (object) $response;
        $status = isset($responseInfo->status) ? (string) $responseInfo->status : null;
        $statusUpper = $status !== null ? strtoupper($status) : '';
        $result = isset($responseInfo->result) ? (object) $responseInfo->result : null;
        $fullResponse = isset($responseInfo->fullResponse) ? (object) $responseInfo->fullResponse : null;

        $rechargeStateCode = '';
        $rechargeState = '';
        $isCompletedRecharge = false;

        if ($fullResponse !== null) {
            $fullResponseObj = isset($fullResponse->saleRecharge) ? (object) $fullResponse->saleRecharge : null;
            if ($fullResponseObj !== null) {
                $rechargeStateCode = (string) ($fullResponseObj->rechargeStateCode ?? '');
                $rechargeState = (string) ($fullResponseObj->rechargeState ?? '');
                $isCompletedRecharge = $statusUpper === 'COMPLETED'
                    && ($rechargeStateCode === 'OK' || $rechargeState === 'Realizada');
            }
        }

        $orderId = $responseInfo->orderId ?? null;
        $fullResponseHasSaleKey = isset($response['fullResponse']['Sale']);

        if ($isCompletedRecharge || $orderId !== null) {
            $canComplete = $isRecharge || $fullResponseHasSaleKey;
            if (!$canComplete) {
                // El original hace un `return;` inmediato aquí, ANTES del flush:
                // ni siquiera persiste la respuesta cruda que acaba de recibir.
                return new ProviderStatusResult(
                    outcome: ProviderOutcomeEnum::PENDING,
                    skip: true,
                    recordHistory: false,
                    abortWithoutPersisting: true,
                    raw: $response,
                );
            }

            return new ProviderStatusResult(
                outcome: ProviderOutcomeEnum::COMPLETED,
                providerReference: $orderId !== null ? (string) $orderId : null,
                raw: $response,
            );
        }

        if (in_array($rechargeStateCode, ['PE', 'PR'], true)) {
            // ETECSA aún procesando la recarga.
            return new ProviderStatusResult(outcome: ProviderOutcomeEnum::PENDING, raw: $response);
        }

        if ($result !== null && $status !== null && ($result->valueOk ?? false) && $status === CommunicationStateEnum::REJECTED->value) {
            return new ProviderStatusResult(outcome: ProviderOutcomeEnum::REJECTED, raw: $response);
        }

        if ($result !== null && !($result->valueOk ?? false)) {
            $code = $result->code ?? null;

            if ($code !== null) {
                $message = $this->errorMessage($code);
                $enrichedRaw = $response;
                if ($message !== null) {
                    $enrichedRaw['result']['message'] = $message;
                }

                if (in_array((string) $code, ['151', '152', '153', '198', '199', '200'], true)) {
                    return new ProviderStatusResult(
                        outcome: ProviderOutcomeEnum::REJECTED,
                        providerCode: (string) $code,
                        message: $message,
                        raw: $enrichedRaw,
                    );
                }

                if ((int) $code !== -1) {
                    return new ProviderStatusResult(
                        outcome: ProviderOutcomeEnum::PENDING,
                        providerCode: (string) $code,
                        message: $message,
                        raw: $enrichedRaw,
                    );
                }

                // code === -1: el original no ejecuta ninguna rama aquí — sin cambios.
                return new ProviderStatusResult(
                    outcome: ProviderOutcomeEnum::PENDING,
                    skip: true,
                    recordHistory: false,
                    raw: $response,
                );
            }

            return new ProviderStatusResult(outcome: ProviderOutcomeEnum::REJECTED, raw: $response);
        }

        if ($result === null) {
            // El original registra este histórico SIN el payload crudo
            // (createHistoricalCommunication con su tercer argumento por defecto).
            return new ProviderStatusResult(
                outcome: ProviderOutcomeEnum::PENDING,
                recordHistoryWithoutData: true,
                raw: $response,
            );
        }

        // $result existe y valueOk es true, pero no coincide con status=Rejected
        // ni con isCompletedRecharge/orderId: el original no ejecuta ninguna rama.
        return new ProviderStatusResult(
            outcome: ProviderOutcomeEnum::PENDING,
            skip: true,
            recordHistory: false,
            raw: $response,
        );
    }
}
