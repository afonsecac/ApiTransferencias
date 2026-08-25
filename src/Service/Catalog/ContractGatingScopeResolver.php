<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Repository\SysConfigRepository;

/**
 * Decide si `PackageCatalogResolver` restringe visibilidad "todo o nada"
 * POR TENANT completo (regla histórica) o POR CATEGORÍA (service/subservice
 * — Fase 2 del rediseño de contratos por categoría) — mismo idioma que
 * `CatalogVersionResolver::isV2()`: sys_config, sin desplegar para
 * activar/desactivar por cliente, dinero real de por medio.
 *
 * Sin ninguna de las dos claves seteadas, isCategoryScoped() siempre es
 * false — "flag OFF = comportamiento idéntico" (ningún cliente existente
 * cambia de comportamiento el día del deploy de esta fase).
 *
 * Precedencia: si el cliente de la cuenta está en la lista explícita de
 * `communications.catalog.contract_gating.pilot_clients`, usa gating por
 * categoría SIEMPRE (aunque el default global siga siendo `tenant`) — así
 * se puede pilotar con un cliente concreto sin mover el default. Si no está
 * en la lista, se usa el default global.
 */
final class ContractGatingScopeResolver
{
    public const SCOPE_KEY = 'communications.catalog.contract_gating_scope';
    public const PILOT_CLIENTS_KEY = 'communications.catalog.contract_gating.pilot_clients';

    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    public function isCategoryScoped(Account $account): bool
    {
        $clientId = $account->getClient()?->getId();
        if ($clientId !== null && in_array((string) $clientId, $this->pilotClientIds(), true)) {
            return true;
        }

        return $this->sysConfigRepo->findCachedValue(self::SCOPE_KEY) === 'category';
    }

    /**
     * @return list<string>
     */
    private function pilotClientIds(): array
    {
        $csv = $this->sysConfigRepo->findCachedValue(self::PILOT_CLIENTS_KEY);
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $id) => $id !== ''));
    }
}
