<?php

namespace App\Provider\Contract;

use App\Enums\ProviderOutcomeEnum;

/**
 * Resultado neutral de una consulta de estado.
 *
 * `skip` reproduce los casos en los que el código original de ETECSA no
 * cambiaba el estado ni registraba histórico (ver
 * EtecsaStatusMapper::mapStatusQuery), pero SÍ persistía la respuesta cruda
 * recibida. `abortWithoutPersisting` reproduce el único caso en el que el
 * código original hacía un `return;` inmediato sin llegar a flush — cuando
 * la venta parece completada pero el tipo de venta no puede confirmarse
 * desde esta respuesta (canComplete = false). Cuando `abortWithoutPersisting`
 * es true, el llamador debe retornar de inmediato sin tocar la entidad.
 * `recordHistoryWithoutData` reproduce el único caso en el que el histórico
 * se registraba SIN el payload crudo (createHistoricalCommunication con su
 * tercer argumento por defecto, `[]`), en vez de con `raw`.
 */
final readonly class ProviderStatusResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public ProviderOutcomeEnum $outcome,
        public ?string $providerReference = null,
        public ?string $providerCode = null,
        public ?string $message = null,
        public bool $recordHistory = true,
        public bool $skip = false,
        public bool $abortWithoutPersisting = false,
        public bool $recordHistoryWithoutData = false,
        public array $raw = [],
    ) {
    }
}
