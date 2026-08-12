<?php

namespace App\Provider;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusResult;

/**
 * Sobre homologado para CommunicationSaleInfo::$transactionStatus y
 * CommunicationSaleHistory::$info — reemplaza la respuesta cruda de cada
 * proveedor (forma distinta por proveedor, y hasta ~4 formas adicionales
 * armadas a mano por CommunicationSaleService para sus propios fallos
 * internos) por un formato único, sin importar el proveedor ni si el fallo
 * vino de un proveedor real o de una validación nuestra.
 *
 * v1 = ausencia de la clave `schemaVersion` (respuesta cruda de ETECSA tal
 * cual, ~5060 filas históricas) — nunca se reescribe histórico, así que
 * SIEMPRE hay que soportar leer v1 y v2 indistintamente. Nunca se escribe
 * `schemaVersion: 1` ni `null`; solo ausente o `2`.
 *
 * @phpstan-type Envelope array{
 *     schemaVersion: int,
 *     source: string,
 *     outcome: string,
 *     provider: ?string,
 *     providerReference: ?string,
 *     providerCode: ?string,
 *     message: ?string,
 *     occurredAt: string,
 *     raw: array<string, mixed>,
 *     context?: array<string, mixed>,
 *     retry?: array<string, mixed>,
 * }
 */
final class TransactionStatus
{
    public const VERSION = 2;
    public const KEY = 'schemaVersion';
    public const SOURCE_PROVIDER = 'provider';
    public const SOURCE_INTERNAL = 'internal';

    // ---- escritura ---------------------------------------------------

    /**
     * @param array<string, mixed> $context
     * @return Envelope
     */
    public static function fromDispatch(ProviderDispatchResult $result, ?string $provider, array $context = []): array
    {
        return self::envelope(
            source: self::SOURCE_PROVIDER,
            outcome: $result->outcome,
            provider: $provider,
            providerReference: $result->providerReference,
            providerCode: $result->providerCode,
            message: $result->message,
            raw: $result->raw,
            context: $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return Envelope
     */
    public static function fromStatus(ProviderStatusResult $result, ?string $provider, array $context = []): array
    {
        return self::envelope(
            source: self::SOURCE_PROVIDER,
            outcome: $result->outcome,
            provider: $provider,
            providerReference: $result->providerReference,
            providerCode: $result->providerCode,
            message: $result->message,
            raw: $result->raw,
            context: $context,
        );
    }

    /**
     * Fallo sin round-trip real a un proveedor: validaciones nuestras
     * (saldo insuficiente, usuario/entorno inesperado, duplicado...).
     * `raw` siempre vacío — no hay respuesta de proveedor que conservar.
     *
     * @param array<string, mixed> $context
     * @return Envelope
     */
    public static function internal(
        ProviderOutcomeEnum $outcome,
        string $code,
        ?string $message = null,
        ?string $provider = null,
        array $context = [],
    ): array {
        return self::envelope(
            source: self::SOURCE_INTERNAL,
            outcome: $outcome,
            provider: $provider,
            providerReference: null,
            providerCode: $code,
            message: $message,
            raw: [],
            context: $context,
        );
    }

    /**
     * Igual que internal(), pero PRESERVA raw/providerReference/provider del
     * sobre previo si ese sobre era source=provider — para catches que
     * ocurren DESPUÉS de un round-trip real al proveedor (ver
     * CommunicationSaleService: catch genérico tras dispatch, catch 400 en
     * checkStatusOrder()). Sin esto, esos catches pisan la única evidencia
     * de que la operación llegó a enviarse.
     *
     * @param array<string, mixed> $current $sale->getTransactionStatus() previo
     * @param array<string, mixed> $context
     * @return Envelope
     */
    public static function internalPreserving(
        array $current,
        ProviderOutcomeEnum $outcome,
        string $code,
        ?string $message = null,
        array $context = [],
    ): array {
        $preservedRaw = self::isV2($current) && ($current['source'] ?? null) === self::SOURCE_PROVIDER
            ? ($current['raw'] ?? [])
            : [];
        $preservedReference = self::isV2($current) && ($current['source'] ?? null) === self::SOURCE_PROVIDER
            ? ($current['providerReference'] ?? null)
            : null;
        $preservedProvider = self::isV2($current) ? ($current['provider'] ?? null) : null;

        return self::envelope(
            source: self::SOURCE_INTERNAL,
            outcome: $outcome,
            provider: $preservedProvider,
            providerReference: $preservedReference,
            providerCode: $code,
            message: $message,
            raw: $preservedRaw,
            context: $context,
        );
    }

    /**
     * Añade/actualiza el bloque `retry` sin tocar el resto del sobre. Si
     * `$current` es v1 (legacy) o está vacío, lo envuelve en un sobre v2
     * nuevo con `raw` = `$current` tal cual.
     *
     * @param array<string, mixed> $current
     * @param array{count?: int, lastAttemptAt?: string, gatewayMissing?: bool, markedRejectedAt?: string, reason?: string} $retry
     * @return Envelope
     */
    public static function withRetry(
        array $current,
        ProviderOutcomeEnum $outcome,
        string $code,
        ?string $message,
        array $retry,
    ): array {
        $envelope = self::isV2($current)
            ? $current
            : self::envelope(
                source: self::SOURCE_PROVIDER,
                outcome: $outcome,
                provider: $current['provider'] ?? null,
                providerReference: null,
                providerCode: $code,
                message: $message,
                raw: $current,
            );

        $envelope['outcome'] = $outcome->value;
        $envelope['providerCode'] = $code;
        $envelope['message'] = $message;
        $envelope['occurredAt'] = self::now();
        $envelope['retry'] = $retry;

        /** @var Envelope $envelope */
        return $envelope;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $context
     * @return Envelope
     */
    private static function envelope(
        string $source,
        ProviderOutcomeEnum $outcome,
        ?string $provider,
        ?string $providerReference,
        ?string $providerCode,
        ?string $message,
        array $raw,
        array $context = [],
    ): array {
        $envelope = [
            self::KEY => self::VERSION,
            'source' => $source,
            'outcome' => $outcome->value,
            'provider' => $provider,
            'providerReference' => $providerReference,
            'providerCode' => $providerCode,
            'message' => $message,
            'occurredAt' => self::now(),
            'raw' => $raw,
        ];
        if ($context !== []) {
            $envelope['context'] = $context;
        }

        /** @var Envelope $envelope */
        return $envelope;
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
    }

    // ---- lectura, compatible v1/v2 ------------------------------------

    /**
     * @param array<string, mixed> $status
     */
    public static function isV2(array $status): bool
    {
        return isset($status[self::KEY]) && is_int($status[self::KEY]);
    }

    /**
     * Respuesta cruda del proveedor. Para v1, el array entero YA ES la
     * respuesta cruda (no hay envoltorio que quitar).
     *
     * @param array<string, mixed> $status
     * @return array<string, mixed>
     */
    public static function rawOf(array $status): array
    {
        if (self::isV2($status)) {
            return is_array($status['raw'] ?? null) ? $status['raw'] : [];
        }

        return $status;
    }

    /**
     * @param array<string, mixed> $status
     */
    public static function outcomeOf(array $status): ?ProviderOutcomeEnum
    {
        if (!self::isV2($status)) {
            return null;
        }

        return ProviderOutcomeEnum::tryFrom((string) ($status['outcome'] ?? ''));
    }

    /**
     * Doble fallback OBLIGATORIO: ventas con contador de reintentos en
     * formato legacy (clave `retryCount` en la raíz, escrita antes de este
     * cambio) deben seguir leyéndose correctamente — perder el contador
     * reenviaría la recarga y podría cobrar dos veces.
     *
     * @param array<string, mixed> $status
     */
    public static function retryCountOf(array $status): int
    {
        return (int) ($status['retry']['count'] ?? $status['retryCount'] ?? 0);
    }

    /**
     * @param array<string, mixed> $status
     */
    public static function lastRetryAtOf(array $status): ?string
    {
        return $status['retry']['lastAttemptAt'] ?? $status['lastRetryAt'] ?? null;
    }
}
