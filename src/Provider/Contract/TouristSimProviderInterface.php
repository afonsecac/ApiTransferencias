<?php

namespace App\Provider\Contract;

/**
 * Operaciones de SIM Temporal TURISTA — exclusivas de ETECSA, no forman parte
 * de la abstracción común de recarga/paquete. Un cliente ruteado a otro
 * proveedor no puede consumir estos endpoints: ProviderRegistry::getFor()
 * lanza PROVIDER_CAPABILITY_UNSUPPORTED si el proveedor resuelto no
 * implementa esta interfaz.
 */
interface TouristSimProviderInterface extends CommunicationProviderInterface
{
    public function checkPhone(ProviderContext $context, string $phoneNumber): ProviderStatusResult;

    /**
     * @param array<string, mixed>|null $client
     */
    public function sellTouristSim(
        ProviderContext $context,
        string $transactionId,
        string $phoneNumber,
        string $packageCode,
        ?array $client = null,
    ): ProviderDispatchResult;

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    public function sellTouristSimBatch(
        ProviderContext $context,
        string $transactionId,
        string $packageCode,
        array $clients = [],
    ): ProviderDispatchResult;

    public function fetchTouristSaleInfo(ProviderContext $context, string $transactionId, ?string $orderId = null): ProviderStatusResult;

    public function fetchTouristBatchInfo(ProviderContext $context, string $transactionId, ?string $orderId = null): ProviderStatusResult;
}
