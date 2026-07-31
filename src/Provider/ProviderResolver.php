<?php

namespace App\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationProviderEnum;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use Psr\Log\LoggerInterface;

/**
 * Resuelve qué proveedor procesa una venta. La decisión vive aquí y en
 * ningún otro sitio.
 *
 * Fase 2: consulta client_provider_routing con precedencia de más a menos
 * específico. Kill switch: si communications.provider.routing.enabled='0',
 * se ignora la tabla y se devuelve siempre el proveedor por defecto —
 * permite revertir un incidente de routing sin desplegar.
 */
final class ProviderResolver
{
    public const DEFAULT_PROVIDER_KEY = 'communications.provider.default';
    public const ROUTING_ENABLED_KEY = 'communications.provider.routing.enabled';

    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Admisión: qué proveedor procesa una venta NUEVA de esta cuenta.
     */
    public function resolveForAccount(Account $account, ?string $saleType = null): CommunicationProviderEnum
    {
        $client = $account->getClient();
        if ($client === null) {
            return $this->defaultProvider();
        }

        return $this->resolveEffectiveFor($client->getId(), $account->getEnvironment()?->getId(), $saleType);
    }

    /**
     * Misma resolución de resolveForAccount() pero por IDs sueltos, para
     * consumidores que no tienen una Account a mano (p. ej. la vista previa
     * de impacto del dashboard de administración).
     */
    public function resolveEffectiveFor(int $clientId, ?int $environmentId, ?string $saleType): CommunicationProviderEnum
    {
        if (!$this->routingEnabled()) {
            return $this->defaultProvider();
        }

        $routing = $this->routingRepo->findResolvedFor($clientId, $environmentId, $saleType);
        if ($routing === null) {
            return $this->defaultProvider();
        }

        return CommunicationProviderEnum::tryFrom($routing->getProvider()) ?? $this->defaultProvider();
    }

    /**
     * Despacho/consulta: nunca re-resuelve — lee el snapshot persistido en
     * la venta. Esto es lo que permite que checkStatusSaleInfo consulte al
     * proveedor correcto tras reiniciar workers o cambiar el routing.
     */
    public function resolveForSale(CommunicationSaleInfo $sale): CommunicationProviderEnum
    {
        $raw = $sale->getProvider();
        if ($raw === null) {
            $this->logger->warning("Sale {$sale->getId()}: sin proveedor registrado, se asume ETECSA.");

            return CommunicationProviderEnum::ETECSA;
        }

        $provider = CommunicationProviderEnum::tryFrom($raw);
        if ($provider === null) {
            $this->logger->warning("Sale {$sale->getId()}: proveedor '{$raw}' desconocido, se asume ETECSA.");

            return CommunicationProviderEnum::ETECSA;
        }

        return $provider;
    }

    /**
     * Qué proveedores puede consumir un cliente (catálogo publicable + guard
     * de admisión). Sin filas de routing activas, solo el proveedor por
     * defecto.
     *
     * @return list<CommunicationProviderEnum>
     */
    public function allowedForClient(Client $client, ?int $environmentId = null): array
    {
        if (!$this->routingEnabled()) {
            return [$this->defaultProvider()];
        }

        $codes = $this->routingRepo->findActiveProviderCodesForClient($client->getId());
        if (empty($codes)) {
            return [$this->defaultProvider()];
        }

        $providers = array_values(array_filter(array_map(
            static fn (string $code) => CommunicationProviderEnum::tryFrom($code),
            $codes,
        )));

        return empty($providers) ? [$this->defaultProvider()] : $providers;
    }

    private function routingEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(self::ROUTING_ENABLED_KEY) !== '0';
    }

    private function defaultProvider(): CommunicationProviderEnum
    {
        $raw = $this->sysConfigRepo->findCachedValue(self::DEFAULT_PROVIDER_KEY);
        $provider = $raw !== null ? CommunicationProviderEnum::tryFrom($raw) : null;

        return $provider ?? CommunicationProviderEnum::ETECSA;
    }
}
